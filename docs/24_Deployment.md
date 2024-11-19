# Развёртывание приложения

## Устанавливаем и настраиваем Gitlab

1. В отдельной директории создаём файл `docker-compose.yml`
    ```yaml
    version: '3.7'

    services:
      gitlab:
        image: gitlab/gitlab-ee:latest
        container_name: gitlab
        restart: always
        hostname: 'gitlab'
        environment:
          GITLAB_OMNIBUS_CONFIG: |
            external_url 'http://gitlab:7778'
            gitlab_rails['gitlab_shell_ssh_port'] = 9022
        ports:
          - '7778:7778'
          - '9022:22'
        volumes:
          - gitlab_config:/etc/gitlab
          - gitlab_logs:/var/log/gitlab
          - gitlab_data:/var/opt/gitlab
        shm_size: '256m'

    volumes:
       gitlab_config:
       gitlab_logs:
       gitlab_data:
    ```
2. Прописываем адрес `gitlab` в `/etc/hosts`
3. Запускаем контейнеры командой `docker-compose up -d`
4. Дождаться запуска Gitlab
5. Зайти в контейнер Gitlab командой `docker exec -it gitlab sh`
6. В контейнере открыть консоль Rails командой `gitlab-rails console -e production`
7. В консоли ввести команды (`PASSWORD` – требуемый пароль):
    ```
    user = User.where(id: 1).first
    user.password = PASSWORD
    user.password_confirmation = PASSWORD
    user.save
    exit
    ```
8. В хост-системе создаём ключи для работы с Gitlab командой `ssh-keygen -t rsa -b 2048`, указываем путь до своей
   директории `.ssh` и своё имя файла
9. В хост-системе выполняем команду (`PATH` – указанный на предыдущем шаге путь)
    ```shell
    sudo chmod 600 PATH
    eval $(ssh-agent -s)
    ssh-add PATH
    ```
10. Заходим в браузере по адресу `http://localhost:7778`
    1. логинимся с логином `root` и указанным паролем
    2. Создаём публичную группу и публичный репозиторий в ней
    3. Заходим в редактирование профиля и добавляем SSH-ключ
11. В хост-системе клонируем репозиторий, помещаем в него код проекта и пушим обратно в гитлаб

## Устанавливаем и настраиваем Gitlab Runner

1. В файле `docker-compose.yml` добавляем новый сервис
    ```yaml
    gitlab-runner:
      image: gitlab/gitlab-runner:latest
      container_name: gitlab-runner
      restart: always
    ```
2. Запускаем контейнеры командой `docker-compose up -d`
3. Заходим в Gitlab-репозиторий в браузере, переходим на вкладку Settings -> CI/CD -> Runners, копируем команду
   для регистрации runner'а без префикса `sudo`
4. Входим в контейнер командой `docker exec -it gitlab-runner sh`
5. Выполняем команду регистрации, соглашаемся со всеми значениями по умолчанию, в качестве `executor` выбираем `shell`
6. Проверяем в интерфейсе, что runner появился

## Настраиваем виртуальную машину

1. Заходим в виртуальную машину и устанавливаем окружение командами (команды для ubuntu 24.04)
    ```shell
    sudo apt update
    sudo apt install curl git unzip nginx postgresql postgresql-contrib rabbitmq-server supervisor memcached libmemcached-tools redis-server php8.3-cli php8.3-fpm php8.3-common php8.3-mysql php8.3-zip php8.3-gd php8.3-mbstring php8.3-curl php8.3-xml php8.3-bcmath php8.3-pgsql php8.3-memcached php8.3-redis php8.3-igbinary php8.3-msgpack
    ```
2. Устанавливаем composer
    ```shell
    curl -sS https://getcomposer.org/installer -o composer-setup.php
    sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
    ```
3. Создаём БД командами
    ```shell
    sudo -u postgres bash -c "psql -c \"CREATE DATABASE twitter ENCODING 'UTF8' TEMPLATE = template0\""
    sudo -u postgres bash -c "psql -c \"CREATE USER my_user WITH PASSWORD '1H8a61ceQW7htGRE6iVz'\""
    sudo -u postgres bash -c "psql -c \"GRANT ALL PRIVILEGES ON DATABASE twitter TO my_user\""
    sudo -u postgres bash -c "psql -c \"ALTER DATABASE twitter OWNER TO my_user\""
    ```
4. Разрешаем доступ к БД снаружи
    1. В файл `/etc/postgresql/16/main/pg_hba.conf` добавляем строки
        ```
        host    all     all     0.0.0.0/0       md5
        host    all     all     ::/0            md5
        ```
    2. В файле `/etc/postgresql/16/main/postgresql.conf` находим закомментированную строку с параметром
       `listen_addresses` и заменяем её на
        ```
        listen_addresses='*'
        ```
    3. Перезапускаем сервис `postgresql` командой `sudo service postgresql restart`
5. Проверяем, что по порту 5432 можем попасть в БД twitter с реквизитами my_user / 1H8a61ceQW7htGRE6iVz
6. Конфигурируем RabbitMQ командами (по одной команде за раз)
    ```shell
    sudo rabbitmq-plugins enable rabbitmq_management 
    sudo rabbitmq-plugins enable rabbitmq_consistent_hash_exchange
    sudo rabbitmqctl add_user my_user T1y04lWk167MkyEK3YFk
    sudo rabbitmqctl set_user_tags my_user administrator
    sudo rabbitmqctl set_permissions -p / my_user ".*" ".*" ".*"
    ```
7. Проверяем, что по порту 15672 можем залогиниться с указанными кредами
8. Дадим права на работу с каталогом `var/www` всем командой `sudo chmod 777 /var/www`
9. В файле `/etc/nginx/nginx.conf` исправляем строку (актуально для AWS EC2)
    ```
    server_name_hash_bucket_size 128;
    ```
10. Перезапускаем nginx командой `sudo service nginx restart`
11. В файл `/etc/sudoers` добавляем строку
     ```
     www-data ALL=(ALL) NOPASSWD:ALL
     ```

## Делаем копию репозитория

1. Для доступа извне понадобится копия репозитория, доступная с виртуальной машины.
2. Создаём, например, в github копию репозитория и подключаем её к локальному репозиторию командой
   `git remote add public PATH` (PATH – путь к репозиторию)
3. Пушим код в новый репозиторий с ключом `--force`

## Добавляем скрипт развёртывания

1. В репозитории в GitLab
    1. заходим в раздел `Settings -> CI / CD` и добавляем переменные окружения
        1. `SERVER1` - адрес сервера
        2. `SSH_USER` - имя пользователя для входа по ssh (для AWS EC2 - `ubuntu`)
        3. `SSH_PRIVATE_KEY` - приватный ключ, закодированный в base64
        4. `DATABASE_HOST` - `localhost`
        5. `DATABASE_NAME` - `twitter`
        6. `DATABASE_USER` - `my_user`
        7. `DATABASE_PASSWORD` - `1H8a61ceQW7htGRE6iVz`
        8. `RABBITMQ_HOST` - `localhost`
        9. `RABBITMQ_USER` - `my_user`
        10. `RABBITMQ_PASSWORD` - `T1y04lWk167MkyEK3YFk`
2. Создаём файл `deploy/nginx.conf`
    ```
    server {
        listen 80;
    
        server_name %SERVER_NAME%;
        error_log  /var/log/nginx/error.log;
        access_log /var/log/nginx/access.log;
        root /var/www/demo/public;
    
        rewrite ^/index\.php/?(.*)$ /$1 permanent;
    
        try_files $uri @rewriteapp;
    
        location @rewriteapp {
            rewrite ^(.*)$ /index.php/$1 last;
        }
    
        # Deny all . files
        location ~ /\. {
            deny all;
        }
    
        location ~ ^/index\.php(/|$) {
            fastcgi_split_path_info ^(.+\.php)(/.*)$;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            fastcgi_param PATH_INFO $fastcgi_path_info;
            fastcgi_index index.php;
            send_timeout 1800;
            fastcgi_read_timeout 1800;
            fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        }
    }
    ```
3. Создаём файл `deploy/supervisor.conf`
    ```
    [program:add_followers]
    command=php -dmemory_limit=1G /var/www/demo/bin/console rabbitmq:consumer -m 100 add_followers --env=dev -vv
    process_name=add_follower_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/var/www/demo/var/log/supervisor.add_followers.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/var/www/demo/var/log/supervisor.add_followers.error.log
    stderr_capture_maxbytes=1MB
    
    [program:publish_tweet]
    command=php -dmemory_limit=1G /var/www/demo/bin/console rabbitmq:consumer -m 100 publish_tweet --env=dev -vv
    process_name=publish_tweet_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/var/www/demo/var/log/supervisor.publish_tweet.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/var/www/demo/var/log/supervisor.publish_tweet.error.log
    stderr_capture_maxbytes=1MB
    
    [program:send_notification_email]
    command=php -dmemory_limit=1G /var/www/demo/bin/console rabbitmq:consumer -m 100 send_notification.email --env=dev -vv
    process_name=send_notification_email_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/var/www/demo/var/log/supervisor.send_notification_email.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/var/www/demo/var/log/supervisor.send_notification_email.error.log
    stderr_capture_maxbytes=1MB
    
    [program:send_notification_sms]
    command=php -dmemory_limit=1G /var/www/demo/bin/console rabbitmq:consumer -m 100 send_notification.sms --env=dev -vv
    process_name=send_notification_sms_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/var/www/demo/var/log/supervisor.send_notification_sms.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/var/www/demo/var/log/supervisor.send_notification_sms.error.log
    stderr_capture_maxbytes=1MB
    
    [program:update_feed_0]
    command=php -dmemory_limit=1G /var/www/demo/bin/console rabbitmq:consumer -m 100 update_feed_0 --env=dev -vv
    process_name=update_feed_0_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/var/www/demo/var/log/supervisor.update_feed.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/var/www/demo/var/log/supervisor.update_feed.error.log
    stderr_capture_maxbytes=1MB
    
    [program:update_feed_1]
    command=php -dmemory_limit=1G /var/www/demo/bin/console rabbitmq:consumer -m 100 update_feed_1 --env=dev -vv
    process_name=update_feed_1_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/var/www/demo/var/log/supervisor.update_feed.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/var/www/demo/var/log/supervisor.update_feed.error.log
    stderr_capture_maxbytes=1MB
    
    [program:update_feed_2]
    command=php -dmemory_limit=1G /var/www/demo/bin/console rabbitmq:consumer -m 100 update_feed_2 --env=dev -vv
    process_name=update_feed_2_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/var/www/demo/var/log/supervisor.update_feed.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/var/www/demo/var/log/supervisor.update_feed.error.log
    stderr_capture_maxbytes=1MB
    
    [program:update_feed_3]
    command=php -dmemory_limit=1G /var/www/demo/bin/console rabbitmq:consumer -m 100 update_feed_3 --env=dev -vv
    process_name=update_feed_3_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/var/www/demo/var/log/supervisor.update_feed.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/var/www/demo/var/log/supervisor.update_feed.error.log
    stderr_capture_maxbytes=1MB
    
    [program:update_feed_4]
    command=php -dmemory_limit=1G /var/www/demo/bin/console rabbitmq:consumer -m 100 update_feed_4 --env=dev -vv
    process_name=update_feed_4_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/var/www/demo/var/log/supervisor.update_feed.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/var/www/demo/var/log/supervisor.update_feed.error.log
    stderr_capture_maxbytes=1MB
    
    [program:update_feed_5]
    command=php -dmemory_limit=1G /var/www/demo/bin/console rabbitmq:consumer -m 100 update_feed_5 --env=dev -vv
    process_name=update_feed_5_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/var/www/demo/var/log/supervisor.update_feed.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/var/www/demo/var/log/supervisor.update_feed.error.log
    stderr_capture_maxbytes=1MB
    
    [program:update_feed_6]
    command=php -dmemory_limit=1G /var/www/demo/bin/console rabbitmq:consumer -m 100 update_feed_6 --env=dev -vv
    process_name=update_feed_6_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/var/www/demo/var/log/supervisor.update_feed.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/var/www/demo/var/log/supervisor.update_feed.error.log
    stderr_capture_maxbytes=1MB
    
    [program:update_feed_7]
    command=php -dmemory_limit=1G /var/www/demo/bin/console rabbitmq:consumer -m 100 update_feed_7 --env=dev -vv
    process_name=update_feed_7_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/var/www/demo/var/log/supervisor.update_feed.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/var/www/demo/var/log/supervisor.update_feed.error.log
    stderr_capture_maxbytes=1MB
    
    [program:update_feed_8]
    command=php -dmemory_limit=1G /var/www/demo/bin/console rabbitmq:consumer -m 100 update_feed_8 --env=dev -vv
    process_name=update_feed_8_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/var/www/demo/var/log/supervisor.update_feed.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/var/www/demo/var/log/supervisor.update_feed.error.log
    stderr_capture_maxbytes=1MB
    
    [program:update_feed_9]
    command=php -dmemory_limit=1G /var/www/demo/bin/console rabbitmq:consumer -m 100 update_feed_9 --env=dev -vv
    process_name=update_feed_9_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/var/www/demo/var/log/supervisor.update_feed.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/var/www/demo/var/log/supervisor.update_feed.error.log
    stderr_capture_maxbytes=1MB 
    ```
4. Исправляем файл `.env`
    ```shell
    # In all environments, the following files are loaded if they exist,
    # the latter taking precedence over the former:
    #
    #  * .env                contains default values for the environment variables needed by the app
    #  * .env.local          uncommitted file with local overrides
    #  * .env.$APP_ENV       committed environment-specific defaults
    #  * .env.$APP_ENV.local uncommitted environment-specific overrides
    #
    # Real environment variables win over .env files.
    #
    # DO NOT DEFINE PRODUCTION SECRETS IN THIS FILE NOR IN ANY OTHER COMMITTED FILES.
    # https://symfony.com/doc/current/configuration/secrets.html
    #
    # Run "composer dump-env prod" to compile .env files for production use (requires symfony/flex >=1.2).
    # https://symfony.com/doc/current/best_practices.html#use-environment-variables-for-infrastructure-configuration
    
    ###> symfony/framework-bundle ###
    APP_ENV=dev
    APP_SECRET=ef149c449150e776ba9f6e489828fc55
    ###< symfony/framework-bundle ###
    
    ###> doctrine/doctrine-bundle ###
    # Format described at https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html#connecting-using-a-url
    # IMPORTANT: You MUST configure your server version, either here or in config/packages/doctrine.yaml
    #
    # DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
    # DATABASE_URL="mysql://app:!ChangeMe!@127.0.0.1:3306/app?serverVersion=8.0.32&charset=utf8mb4"
    # DATABASE_URL="mysql://app:!ChangeMe!@127.0.0.1:3306/app?serverVersion=10.11.2-MariaDB&charset=utf8mb4"
    DATABASE_URL="postgresql://%DATABASE_USER%:%DATABASE_PASSWORD%@%DATABASE_HOST%:5432/%DATABASE_NAME%?serverVersion=15&charset=utf8"
    ###< doctrine/doctrine-bundle ###
    
    ###> lexik/jwt-authentication-bundle ###
    JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
    JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
    JWT_PASSPHRASE=94d167afb5f515307526969caf8c87fbb14e0563f69839c10590802040dea7d7
    JWT_TTL_SEC=3600
    ###< lexik/jwt-authentication-bundle ###
    
    MEMCACHED_DSN=memcached://localhost:11211
    REDIS_DSN=redis://localhost:6379
    
    ###> php-amqplib/rabbitmq-bundle ###
    RABBITMQ_URL=amqp://%RABBITMQ_USER%:%RABBITMQ_PASSWORD%@%RABBITMQ_HOST%:5672
    RABBITMQ_VHOST=/
    ###< php-amqplib/rabbitmq-bundle ###
    
    ###> friendsofsymfony/elastica-bundle ###
    ELASTICSEARCH_URL=http://elasticsearch:9200/
    ###< friendsofsymfony/elastica-bundle ###
    
    ###> symfony/lock ###
    # Choose one of the stores below
    # postgresql+advisory://db_user:db_password@localhost/db_name
    LOCK_DSN=flock
    ###< symfony/lock ###
    ```
5. Создаём файл `deploy.sh`
    ```shell
    sudo cp deploy/nginx.conf /etc/nginx/conf.d/demo.conf -f
    sudo cp deploy/supervisor.conf /etc/supervisor/conf.d/demo.conf -f
    sudo sed -i -- "s|%SERVER_NAME%|$1|g" /etc/nginx/conf.d/demo.conf
    sudo service nginx restart
    sudo -u www-data composer install -q
    sudo service php8.3-fpm restart
    sudo -u www-data sed -i -- "s|%DATABASE_HOST%|$2|g" .env
    sudo -u www-data sed -i -- "s|%DATABASE_USER%|$3|g" .env
    sudo -u www-data sed -i -- "s|%DATABASE_PASSWORD%|$4|g" .env
    sudo -u www-data sed -i -- "s|%DATABASE_NAME%|$5|g" .env
    sudo -u www-data php bin/console doctrine:migrations:migrate --no-interaction
    sudo -u www-data sed -i -- "s|%RABBITMQ_HOST%|$6|g" .env
    sudo -u www-data sed -i -- "s|%RABBITMQ_USER%|$7|g" .env
    sudo -u www-data sed -i -- "s|%RABBITMQ_PASSWORD%|$8|g" .env
    sudo service supervisor restart
    ```
6. Создаём файл `.gitlab-ci.yml` (не забудьте указать корректный путь к репозиторию в git clone и креды)
    ```yml
    before_script:
      - eval $(ssh-agent -s)
      - ssh-add <(echo "$SSH_PRIVATE_KEY" | base64 -d)
      - mkdir -p ~/.ssh
      - echo -e "Host *\n\tStrictHostKeyChecking no\n\n" > ~/.ssh/config
    
    deploy_server1:
      stage: deploy
      environment:
        name: server1
        url: $SERVER1
      script:
        - ssh $SSH_USER@$SERVER1 "sudo rm -rf /var/www/demo &&
          cd /var/www &&
          git clone https://github.com/otusteamedu/symfony-deploy-2024-11.git demo &&
          sudo chown www-data:www-data demo -R &&
          cd demo &&
          sh ./deploy.sh $SERVER1 $DATABASE_HOST $DATABASE_USER $DATABASE_PASSWORD $DATABASE_NAME $RABBITMQ_HOST $RABBITMQ_USER $RABBITMQ_PASSWORD"
      only:
        - main
    ```
7. Добавляем код в main-ветку и пушим в GitLab и в публичный репозиторий
8. В репозитории в GitLab в разделе `CI / CD -> Pipelines` можно следить за процессом
9. Проверяем в интерфейсе RabbitMQ, что консьюмеры запустились
10. Выполняем запрос Add user v2 из Postman-коллекции v10 с заменой переменной host на адрес сервера

## Переходим на blue-green deploy

1. На сервере
    1. удаляем на сервере содержимое каталог `/var/www/demo`
    2. создаём каталог `/var/www/demo/shared/log`
    3. выполняем команду `sudo chmod 777 /var/www/demo -R`
2. Исправляем файл `deploy/nginx.conf`
    ```
    server {
        listen 80;
    
        server_name %SERVER_NAME%;
        error_log  /var/log/nginx/error.log;
        access_log /var/log/nginx/access.log;
        root /var/www/demo/current/public;
    
        rewrite ^/index\.php/?(.*)$ /$1 permanent;
    
        try_files $uri @rewriteapp;
    
        location @rewriteapp {
            rewrite ^(.*)$ /index.php/$1 last;
        }
    
        # Deny all . files
        location ~ /\. {
            deny all;
        }
    
        location ~ ^/index\.php(/|$) {
            fastcgi_split_path_info ^(.+\.php)(/.*)$;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            fastcgi_param PATH_INFO $fastcgi_path_info;
            fastcgi_index index.php;
            send_timeout 1800;
            fastcgi_read_timeout 1800;
            fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        }
    }
    ```
3. Исправлям файл `deploy/supervisor.conf`
    ```
    [program:add_followers]
    command=php -dmemory_limit=1G /var/www/demo/current/bin/console rabbitmq:consumer -m 100 add_followers --env=dev -vv
    process_name=add_follower_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/var/www/demo/current/var/log/supervisor.add_followers.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/var/www/demo/current/var/log/supervisor.add_followers.error.log
    stderr_capture_maxbytes=1MB
    
    [program:publish_tweet]
    command=php -dmemory_limit=1G /var/www/demo/current/bin/console rabbitmq:consumer -m 100 publish_tweet --env=dev -vv
    process_name=publish_tweet_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/var/www/demo/current/var/log/supervisor.publish_tweet.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/var/www/demo/current/var/log/supervisor.publish_tweet.error.log
    stderr_capture_maxbytes=1MB
    
    [program:send_notification_email]
    command=php -dmemory_limit=1G /var/www/demo/current/bin/console rabbitmq:consumer -m 100 send_notification.email --env=dev -vv
    process_name=send_notification_email_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/var/www/demo/current/var/log/supervisor.send_notification_email.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/var/www/demo/current/var/log/supervisor.send_notification_email.error.log
    stderr_capture_maxbytes=1MB
    
    [program:send_notification_sms]
    command=php -dmemory_limit=1G /var/www/demo/current/bin/console rabbitmq:consumer -m 100 send_notification.sms --env=dev -vv
    process_name=send_notification_sms_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/var/www/demo/current/var/log/supervisor.send_notification_sms.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/var/www/demo/current/var/log/supervisor.send_notification_sms.error.log
    stderr_capture_maxbytes=1MB
    
    [program:update_feed_0]
    command=php -dmemory_limit=1G /var/www/demo/current/bin/console rabbitmq:consumer -m 100 update_feed_0 --env=dev -vv
    process_name=update_feed_0_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/var/www/demo/current/var/log/supervisor.update_feed.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/var/www/demo/current/var/log/supervisor.update_feed.error.log
    stderr_capture_maxbytes=1MB
    
    [program:update_feed_1]
    command=php -dmemory_limit=1G /var/www/demo/current/bin/console rabbitmq:consumer -m 100 update_feed_1 --env=dev -vv
    process_name=update_feed_1_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/var/www/demo/current/var/log/supervisor.update_feed.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/var/www/demo/current/var/log/supervisor.update_feed.error.log
    stderr_capture_maxbytes=1MB
    
    [program:update_feed_2]
    command=php -dmemory_limit=1G /var/www/demo/current/bin/console rabbitmq:consumer -m 100 update_feed_2 --env=dev -vv
    process_name=update_feed_2_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/var/www/demo/current/var/log/supervisor.update_feed.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/var/www/demo/current/var/log/supervisor.update_feed.error.log
    stderr_capture_maxbytes=1MB
    
    [program:update_feed_3]
    command=php -dmemory_limit=1G /var/www/demo/current/bin/console rabbitmq:consumer -m 100 update_feed_3 --env=dev -vv
    process_name=update_feed_3_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/var/www/demo/current/var/log/supervisor.update_feed.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/var/www/demo/current/var/log/supervisor.update_feed.error.log
    stderr_capture_maxbytes=1MB
    
    [program:update_feed_4]
    command=php -dmemory_limit=1G /var/www/demo/current/bin/console rabbitmq:consumer -m 100 update_feed_4 --env=dev -vv
    process_name=update_feed_4_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/var/www/demo/current/var/log/supervisor.update_feed.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/var/www/demo/current/var/log/supervisor.update_feed.error.log
    stderr_capture_maxbytes=1MB
    
    [program:update_feed_5]
    command=php -dmemory_limit=1G /var/www/demo/current/bin/console rabbitmq:consumer -m 100 update_feed_5 --env=dev -vv
    process_name=update_feed_5_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/var/www/demo/current/var/log/supervisor.update_feed.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/var/www/demo/current/var/log/supervisor.update_feed.error.log
    stderr_capture_maxbytes=1MB
    
    [program:update_feed_6]
    command=php -dmemory_limit=1G /var/www/demo/current/bin/console rabbitmq:consumer -m 100 update_feed_6 --env=dev -vv
    process_name=update_feed_6_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/var/www/demo/current/var/log/supervisor.update_feed.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/var/www/demo/current/var/log/supervisor.update_feed.error.log
    stderr_capture_maxbytes=1MB
    
    [program:update_feed_7]
    command=php -dmemory_limit=1G /var/www/demo/current/bin/console rabbitmq:consumer -m 100 update_feed_7 --env=dev -vv
    process_name=update_feed_7_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/var/www/demo/current/var/log/supervisor.update_feed.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/var/www/demo/current/var/log/supervisor.update_feed.error.log
    stderr_capture_maxbytes=1MB
    
    [program:update_feed_8]
    command=php -dmemory_limit=1G /var/www/demo/current/bin/console rabbitmq:consumer -m 100 update_feed_8 --env=dev -vv
    process_name=update_feed_8_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/var/www/demo/current/var/log/supervisor.update_feed.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/var/www/demo/current/var/log/supervisor.update_feed.error.log
    stderr_capture_maxbytes=1MB
    
    [program:update_feed_9]
    command=php -dmemory_limit=1G /var/www/demo/current/bin/console rabbitmq:consumer -m 100 update_feed_9 --env=dev -vv
    process_name=update_feed_9_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/var/www/demo/current/var/log/supervisor.update_feed.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/var/www/demo/current/var/log/supervisor.update_feed.error.log
    stderr_capture_maxbytes=1MB 
    ```
4. Исправляем `.gitlab-ci.yml`
    ```yaml
    before_script:
      - eval $(ssh-agent -s)
      - ssh-add <(echo "$SSH_PRIVATE_KEY" | base64 -d)
      - mkdir -p ~/.ssh
      - echo -e "Host *\n\tStrictHostKeyChecking no\n\n" > ~/.ssh/config
      - export DIR=$(date +%Y%m%d_%H%M%S)
    
    deploy_server1:
      stage: deploy
      environment:
        name: server1
        url: $SERVER1
      script:
        - ssh $SSH_USER@$SERVER1 "cd /var/www/demo &&
          git clone https://github.com/otusteamedu/symfony-deploy-2024-11.git $DIR &&
          sudo chown www-data:www-data $DIR -R &&
          cd $DIR &&
          sh ./deploy.sh $SERVER1 $DATABASE_HOST $DATABASE_USER $DATABASE_PASSWORD $DATABASE_NAME $RABBITMQ_HOST $RABBITMQ_USER $RABBITMQ_PASSWORD &&
          cd .. &&
          rm -rf /var/www/demo/$DIR/var/log &&
          ln -s /var/www/demo/shared/log /var/www/demo/$DIR/var/log &&
          ( [ ! -d /var/www/demo/current ] || mv -Tf /var/www/demo/current /var/www/demo/previous ) &&
          ln -s /var/www/demo/$DIR /var/www/demo/current"
      only:
        - main
    ```
5. Пушим код в репозиторий

## Добавляем rollback

1. Добавляем файл `rollback.sh`
    ```shell
    sudo cp deploy/nginx.conf /etc/nginx/conf.d/demo.conf -f
    sudo cp deploy/supervisor.conf /etc/supervisor/conf.d/demo.conf -f
    sudo sed -i -- "s|%SERVER_NAME%|$1|g" /etc/nginx/conf.d/demo.conf
    sudo service nginx restart
    sudo service php8.3-fpm restart
    sudo -u www-data php bin/console cache:clear
    sudo service supervisor restart
    ```
2. Ещё раз исправляем `.gitlab-ci.yml`
    ```yaml
    stages:
      - deploy
      - rollback

    before_script:
      - eval $(ssh-agent -s)
      - ssh-add <(echo "$SSH_PRIVATE_KEY" | base64 -d)
      - mkdir -p ~/.ssh
      - echo -e "Host *\n\tStrictHostKeyChecking no\n\n" > ~/.ssh/config
      - export DIR=$(date +%Y%m%d_%H%M%S)
    
    deploy_server1:
      stage: deploy
      environment:
        name: server1
        url: $SERVER1
      script:
        - ssh $SSH_USER@$SERVER1 "cd /var/www/demo &&
          git clone https://github.com/otusteamedu/symfony-deploy-2024-11.git $DIR &&
          sudo chown www-data:www-data $DIR -R &&
          cd $DIR &&
          sh ./deploy.sh $SERVER1 $DATABASE_HOST $DATABASE_USER $DATABASE_PASSWORD $DATABASE_NAME $RABBITMQ_HOST $RABBITMQ_USER $RABBITMQ_PASSWORD
          cd .. &&
          rm -rf /var/www/demo/$DIR/var/log &&
          ln -s /var/www/demo/shared/log /var/www/demo/$DIR/var/log &&
          ( [ ! -d /var/www/demo/current ] || mv -Tf /var/www/demo/current /var/www/demo/previous ) &&
          ln -s /var/www/demo/$DIR /var/www/demo/current"
      only:
        - main
   
    rollback:
      stage: rollback
      script:
        - ssh $SSH_USER@$SERVER1 "unlink /var/www/demo/current &&
              mv -Tf /var/www/demo/previous /var/www/demo/current &&
              cd /var/www/demo/current &&
              sh ./rollback.sh $SERVER1"
      when: manual
    ```
