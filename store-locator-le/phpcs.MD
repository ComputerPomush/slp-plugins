# Setup

    PATH=$PATH:~/phpStorm\ Projects/phpcs/bin

# phpStorm

PHP Storm uses the composer.json file.

Click the setting icon in the gutter next to the squiz labs include to jump to the phpStorm configuration entry.

Use the Code | Inspect menu to generate an IDE based report.

# Command Line Usage

## WordPress Standard

    cd ~/WordPress/wp-content/SLP Plugins/store-locator-le
    phpcs --standard=WordPress <file>

Example:

    phpcs --standard=WordPress ./include/unit.SLP_setting.php

## Coding To Wordpress Standards

* [Escaping Output](https://developer.wordpress.org/plugins/security/securing-output/)
*

## Custom Checks

File phpcs.xml must contain the rules for phpcs in the local directory.

    cd ~/WordPress/wp-content/SLP Plugins/store-locator-le
    phpcs <file>

Example:

    phpcs ./include/unit.SLP_setting.php

