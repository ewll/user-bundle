# Ewll/UserBundle

##Installation
```composer require ewll/user-bundle```

Add to packages configuration:
```
ewll_user:
    salt: '%env(EWLL_USER_SALT)%'
    domain: '%env(DOMAIN)%'
    telegram_bot_name: '%env(TELEGRAM_BOT_NAME)%'
    telegram_bot_token: '%env(TELEGRAM_BOT_TOKEN)%'
```
```
monolog:
    handlers:
        user:
            type: rotating_file
            path: "%kernel.logs_dir%/user.%kernel.environment%.log"
            level: info
            channels: [user]
            max_files: 30
```
```
ewll_db:
    bundles:
        - 'EwllUserBundle'
```

Create App\Entity\User

Create the base letter template in ./templates/letter/letterBase.html.twig with:
{% block subject %}{% endblock %}
{% block body %}{% endblock %}
