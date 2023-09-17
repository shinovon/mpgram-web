FROM nginx:1.20.2

ARG UID
ARG PROTO

COPY conf/nginx.conf /etc/nginx/
COPY conf/${PROTO}.conf /etc/nginx/conf.d/default.conf
COPY conf/upstream.conf /etc/nginx/conf.d/

COPY ssl /etc/nginx/ssl

RUN usermod -u ${UID} www-data

CMD ["nginx"]

EXPOSE 80 443
