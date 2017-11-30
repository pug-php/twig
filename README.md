# PugToTwig

This package can convert basic pug codes into **Twig** code. Caution,
this package should not be used to handle **Pug** templates in Symfony,
the right way to do it is to use
[pug-symfony](https://github.com/pug-php/pug-symfony)
and to either polyfill Twig expressions only or delegate expression
handle to the **Twig** code parser.

This package can help you moving from **Pug** to **Twig** by generating
equivalent code or can be used to create a **Pug** input interface
to insert **Twig** templates in an existing application. These are
the only kind of business you should handle with this package.

For a full-featured **Pug** template engine in **Symfony**
[pug-symfony](https://github.com/pug-php/pug-symfony)
is what you need. If you miss a feature, ask in the issues, there
probably already exists a way to do the same, else we'll try
to add it.

## Instalation

First install composer if you have not: https://getcomposer.org/

Then run the following command:
```shell
composer require pug/twig
```

Or if you installed composer locally:
```shell
php composer.phar require pug/twig
```

Extension for Phug/Pug-php to output Twig (PHP pug to twig converter)

**my-pug-input.pug**:
```pug
ul#users
  - for user in users
    li.user
      // comment
      = user.name
      | Email: #{user.email}
      a(href=user.url) Home page
```

**index.php**:
```php
include 'vendor/autoload.php';

echo PugToTwig::convert(file_get_contents('my-pug-input.pug'));
```

Will output:
```twig
<ul id="users">
  {% for user in users %}
    <li class="user">
      {#  comment #}
      {{ user.name | e }}
      Email: {{ user.email | e }}
      <a href="{{ user.url | e }}">Home page</a>
    </li>
  {% endfor %}
</ul>
```

(indentation not guaranteed).

Some features such as mixins could output PHP that you would need
to evaluate:

```
$html = eval('?>' . PugToTwig::convert($pugCode));
```

Warning: you should be sure you don't let user input appears
between `<?php` and `?>`. It should not happen easily because
expressions and codes are turned into **Twig** code that the
PHP `eval` will just ignore, but be careful.

### Not supported features:

- **Mixins with dynamic names**
- **Mixins arguments**: de facto, argument display will become
Twig interpolation and Twig can't handle pug mixins, so
mixins are evaluated before Twig will evaluate code/expressions.