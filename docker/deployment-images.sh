#!/usr/bin/env bash
# Check the merged service model, not the image list (which includes dependencies).
verify_deployment_images() {
    local app_container="${1:-maddrax-app}"
    # The existing app already provides PHP. Send the private resolved model
    # directly to its JSON decoder; never print it or require host-side jq/PHP.
    $COMPOSE config --format json | docker exec -i "$app_container" php -r '
        try {
            $config = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            fwrite(STDERR, "Could not decode the deployment Compose configuration.\n");
            exit(1);
        }
        $expected = [
            "app" => $argv[1], "queue" => $argv[1], "scheduler" => $argv[1],
            "typesense" => $argv[2], "nginx" => $argv[3], "db" => $argv[4],
        ];
        foreach ($expected as $service => $image) {
            if (($config["services"][$service]["image"] ?? null) !== $image) {
                fwrite(STDERR, "Deployment image mismatch for service: ".$service.".\n");
                exit(1);
            }
        }
        echo "Verified scanned image digests for all six deployment services.\n";
    ' "$OMXFC_APP_IMAGE" "$OMXFC_TYPESENSE_IMAGE" "$OMXFC_NGINX_IMAGE" "$OMXFC_DATABASE_IMAGE"
}
