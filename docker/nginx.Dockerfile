FROM nginx:1.31.8-alpine3.24-slim@sha256:4c734d33ee731135dc9f2349914a34551c920f4451fca8bb05bf5880eb9079e8 AS nginx

# Keep the reviewed Nginx release and apply Alpine fixes published since its image.
RUN apk upgrade --no-cache
