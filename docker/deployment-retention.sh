#!/usr/bin/env bash
# Remove only resources recorded by this stack's deployment helper.
cleanup_deployment_retention() {
    local backup_root cutoff directory latest='' id project old_volume new_volume created
    local kept_directory kept_volume volume service tag container configured_image
    local file protected_directories=''
    local -a metadata=()
    local protected_volumes="${OMXFC_APP_VOLUME}" protected_tags='' containers
    backup_root="$STACK_ROOT/.deployment/backups"
    [[ -d "$backup_root" && ! -L "$backup_root" ]] || return 1
    backup_root="$(realpath -- "$backup_root")" || return 1
    [[ "$backup_root" = "$STACK_ROOT/.deployment/backups" ]] || return 1
    cutoff="$(( $(date -u +%s) - 7 * 24 * 60 * 60 ))"
    for file in "${BASE_FILES[@]}"; do
        if [[ "$file" = "$backup_root/"* ]]; then
            id="${file#"$backup_root/"}"; id="${id%%/*}"
            protected_directories+=$'\n'"$backup_root/$id"
        fi
    done

    # Adopt backups produced before retention metadata was introduced, but
    # only when all six exact rollback tags and the owned code volume match.
    # Unknown/manual backups remain untouched.
    for directory in "$backup_root"/*; do
        [[ -d "$directory" && ! -L "$directory" && ! -e "$directory/retention.meta" && -f "$directory/images.yml" && -f "$directory/compose.yml" && -f "$directory/environment" ]] || continue
        id="$(basename "$directory")"
        [[ "$id" =~ ^[0-9]{8}T[0-9]{6}Z$ ]] || continue
        old_volume="$(sed -n 's/^    name: \([a-zA-Z0-9][a-zA-Z0-9_.-]*\)$/\1/p' "$directory/images.yml")" || return 1
        [[ "$old_volume" =~ ^[a-zA-Z0-9][a-zA-Z0-9_.-]*$ ]] || continue
        [[ "$(docker volume inspect "$old_volume" --format '{{ index .Labels "com.docker.compose.project" }}')" = "$PROJECT_NAME" ]] || continue
        [[ "$(docker volume inspect "$old_volume" --format '{{ index .Labels "com.docker.compose.volume" }}')" = app_data ]] || continue
        kept_volume=1
        for service in app queue scheduler db typesense nginx; do
            grep -Fxq -- "    image: omxfc-rollback:$id-$service" "$directory/images.yml" || kept_volume=0
        done
        [[ "$kept_volume" = 1 ]] || continue
        created="$(date -u -d "${id:0:4}-${id:4:2}-${id:6:2} ${id:9:2}:${id:11:2}:${id:13:2}" +%s)" || return 1
        printf '%s\n' "$PROJECT_NAME" "$old_volume" "$old_volume" "$created" > "$directory/retention.meta" || return 1
    done

    # The newest rollback set survives even after a long gap between releases.
    for directory in "$backup_root"/*; do
        [[ -d "$directory" && ! -L "$directory" ]] || continue
        id="$(basename "$directory")"
        [[ "$id" =~ ^[0-9]{8}T[0-9]{6}Z$ && -f "$directory/retention.meta" ]] || continue
        mapfile -t metadata < "$directory/retention.meta" || return 1
        [[ "${#metadata[@]}" = 4 && "${metadata[0]}" = "$PROJECT_NAME" ]] || continue
        [[ "${metadata[1]}" =~ ^[a-zA-Z0-9][a-zA-Z0-9_.-]*$ && "${metadata[2]}" =~ ^[a-zA-Z0-9][a-zA-Z0-9_.-]*$ && "${metadata[3]}" =~ ^[0-9]{10}$ ]] || return 1
        latest="$directory"
    done

    for kept_directory in "$backup_root"/*; do
        [[ -d "$kept_directory" && ! -L "$kept_directory" && -f "$kept_directory/retention.meta" ]] || continue
        mapfile -t metadata < "$kept_directory/retention.meta" || return 1
        [[ "${#metadata[@]}" = 4 && "${metadata[0]}" = "$PROJECT_NAME" ]] || continue
        if [[ "$kept_directory" = "$BACKUP_DIR" || "$kept_directory" = "$latest" || "${metadata[3]}" -ge "$cutoff" ]] || grep -Fxq -- "$kept_directory" <<< "$protected_directories"; then
            protected_volumes+=$'\n'"${metadata[1]}"$'\n'"${metadata[2]}"
        fi
    done
    containers="$($COMPOSE ps --all --quiet)" || return 1
    for container in $containers; do
        configured_image="$(docker inspect "$container" --format '{{.Config.Image}}')" || return 1
        protected_tags+=$'\n'"$configured_image"
    done

    for directory in "$backup_root"/*; do
        [[ -d "$directory" && ! -L "$directory" && -f "$directory/retention.meta" ]] || continue
        id="$(basename "$directory")"
        [[ "$id" =~ ^[0-9]{8}T[0-9]{6}Z$ ]] || continue
        mapfile -t metadata < "$directory/retention.meta" || return 1
        [[ "${#metadata[@]}" = 4 ]] || continue
        project="${metadata[0]}"; old_volume="${metadata[1]}"; new_volume="${metadata[2]}"; created="${metadata[3]}"
        [[ "$project" = "$PROJECT_NAME" && "$directory" != "$BACKUP_DIR" && "$directory" != "$latest" && "$created" -lt "$cutoff" ]] || continue
        grep -Fxq -- "$directory" <<< "$protected_directories" && continue
        [[ "$(realpath -- "$directory")" = "$backup_root/$id" ]] || return 1

        for volume in "$old_volume" "$new_volume"; do
            grep -Fxq -- "$volume" <<< "$protected_volumes" && continue
            # Retained legacy backups also keep their original code volume.
            kept_volume=0
            for kept_directory in "$backup_root"/*; do
                [[ "$kept_directory" != "$directory" && -d "$kept_directory" && ! -L "$kept_directory" && ! -f "$kept_directory/retention.meta" ]] || continue
                if [[ -f "$kept_directory/images.yml" ]] && grep -Fxq -- "    name: $volume" "$kept_directory/images.yml"; then
                    kept_volume=1
                fi
            done
            [[ "$kept_volume" = 0 ]] || continue
            # Docker itself refuses removal of a volume referenced by any
            # container, including stopped containers and foreign projects.
            containers="$(docker ps --all --quiet --filter "volume=$volume")" || return 1
            [[ -z "$containers" ]] || continue
            if docker volume inspect "$volume" >/dev/null 2>&1; then
                [[ "$(docker volume inspect "$volume" --format '{{ index .Labels "com.docker.compose.project" }}')" = "$PROJECT_NAME" ]] || return 1
                [[ "$(docker volume inspect "$volume" --format '{{ index .Labels "com.docker.compose.volume" }}')" = app_data ]] || return 1
                docker volume rm "$volume" >/dev/null || return 1
            fi
        done
        for service in app queue scheduler db typesense nginx; do
            tag="omxfc-rollback:$id-$service"
            grep -Fxq -- "$tag" <<< "$protected_tags" && continue
            if docker image inspect "$tag" >/dev/null 2>&1; then
                docker image rm "$tag" >/dev/null || return 1
            fi
        done
        # Backups contain secrets. Delete only the validated, expired directory
        # after its removable resources have been released successfully.
        rm -rf -- "$directory" || return 1
        echo "Expired deployment rollback set removed: $id"
    done
}
