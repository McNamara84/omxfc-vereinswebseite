FROM nginx:1.30.6-alpine3.24-slim@sha256:3028fc1419f99b95c1c69f844bbaf49dc1b881750c51b497fc721ac4f02a14e6 AS nginx

# Keep the reviewed Nginx release and apply Alpine fixes published since its image.
RUN apk upgrade --no-cache
