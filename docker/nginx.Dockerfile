FROM nginx:1.31-alpine3.24-slim@sha256:f761b94f2cb9e8e05e2943d5f773609596113ef69b54e2433a996d109a8f78b7 AS nginx

# Keep the reviewed Nginx release and apply Alpine fixes published since its image.
RUN apk upgrade --no-cache
