FROM nginx:1.31.8-alpine3.24-slim@sha256:b899b0ef5345e14c01720a5ff83e716bacda65c7077a7363602ff7e7970bf88a AS nginx

# Keep the reviewed Nginx release and apply Alpine fixes published since its image.
RUN apk upgrade --no-cache
