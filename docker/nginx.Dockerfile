FROM nginx:1.31.7-alpine3.24-slim@sha256:ebd058041a732a2b2dc0b606e34572dd8e0034de6a4aa4d50d8c13a240ac6005 AS nginx

# Keep the reviewed Nginx release and apply Alpine fixes published since its image.
RUN apk upgrade --no-cache
