# Keep the stable Typesense binary, but apply Ubuntu security updates that have
# not yet been incorporated into the upstream image (notably OpenSSL).
FROM typesense/typesense:30.2@sha256:610f2d34b1f93d00762869da2c67736775e5798d19a2c8b91b014b8a0cc1e110

RUN apt-get update \
    && apt-get upgrade -y \
    && rm -rf /var/lib/apt/lists/*
