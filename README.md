![Accredible Logo](https://s3.amazonaws.com/accredible-cdn/accredible_logo_sm.png)

# Accredible Moodle Activity Plugin

## Overview
The Accredible platform enables organizations to create, manage and distribute digital credentials as digital certificates or open badges.

An example digital certificate and badge can be viewed here: https://www.credential.net/10000005

This plugin enables you to issue dynamic, digital certificates or open badges on your Moodle instance. They act as a replacement for the PDF certificates normally generated for your courses.

Here's a video showing a tutorial on how to install and start using the plugin: https://youtu.be/h0ORng5TBnU

## Example Output
![Example Digital Certificate](https://s3.amazonaws.com/accredible-cdn/example-digital-certificate.png)

![Example Open Badge](https://s3.amazonaws.com/accredible-cdn/example-digital-badge.png)

## Compatability

This plugin has been tested and is working on Moodle 2.7+ and Moodle 3.1+.

---

## Plugin Installation

There are two installation methods that are available. Follow one of these, then log into your Moodle site as an administrator and visit the notifications page to complete the install.

#### Git

If you have git installed, simply visit the Moodle /mod directory and clone this repo:

    git clone https://github.com/accredible/moodle-mod_accredible.git accredible

#### Download the zip

1. Visit https://github.com/accredible/moodle-mod_accredible and download the zip. 
2. Extract the zip file's contents and **rename it 'accredible'**. You have to rename it for the plugin to work.
3. Place the folder in your /mod folder, inside your Moodle directory.

#### Get your API key

Make sure you have your API key from Accredible. It's available from the settings page on our dashboard: [https://dashboard.accredible.com](https://dashboard.accredible.com).

#### Continue Moodle set up

Start by installing the new plugin (go to Site Administration > Notifications if your Moodle doesn't ask you to install automatically).

![install-image](https://s3.amazonaws.com/accredible-moodle-instructions/install_plugin.png "Installing the plugin")

After clicking 'Upgrade Moodle database now', this is when you'll enter your API key from Accredible.

![api-image](https://s3.amazonaws.com/accredible-moodle-instructions/set_api_key.png "Enter your Accredible API key")

## Creating a Certificate or Badge

#### Add an Activity

Go to the course you want to issue certificates or badges for and add an Accredible activity. First select add activity:

![add-activity](https://s3.amazonaws.com/accredible-moodle-instructions/add_activity1.png)

then select Accredible:

![select-accredible](https://s3.amazonaws.com/accredible-moodle-instructions/add_activity2.png)

Issuing a certificate or badge is easy - choose from 3 issuing options:

- Pick student names and manually issue credentials. Only students that don't already have a credential will show a checkbox.
- Choose the Quiz Activity that represents the **final exam**, and set a minimum grade requirement. Certificates/Badges will get issued as soon as the student receives a grade above the threshold.
- Choose for a student to receive their certificate/badge when they complete the course if you've setup completion tracking.

![settings-image](https://s3.amazonaws.com/accredible-moodle-instructions/activity_settings2.png "Choose how to issue certificates")

*Note: if you set both types of auto-issue criteria, completing either will issue a certificate/badge.*

*Note: Make sure you don't allow students to set their completion of the Accredible activity or they'll be able to issue their own certificates/badges.*

Once you've added the activity to your course we'll auto-create a Group on your Accredible account where these credentials will belong. You'll see this on your dashboard.

![new-group](https://s3.amazonaws.com/accredible-moodle-instructions/new_group.png "Group on Accredible")

Then select a certificate design and or badge design to be able to send out credentails in this group.

![credentials-list](https://s3.amazonaws.com/accredible-moodle-instructions/credentials_list.png "List of certificates and badges")

From now on new certificates and badges will be automatically sent to recipients based upon the criteria you chose.

You are able to add, edit and remove your badges and certificates at any time through the platform.

**Contact us at support@accredible.com if you have issues or ideas on how we can make this integration better.**

### Bug reports

If you discover any bugs, feel free to create an issue on GitHub. Please add as much information as possible to help us fixing the possible bug. We also encourage you to help even more by forking and sending us a pull request.

https://github.com/accredible/acms-php-api/issues

## FAQs

#### Why is nothing showing up? I can't see a certificate.

A certificate isn't created until you've either manually created one or had a student go through the criteria you set on the activity. For example if you select some required activities then a certificate won't be created until an enrolled student has completed them. Completing an activity or quiz as a course admin won't create a certificate.

---

## Development Information

### Development setup

#### Prerequisites

- [Docker](https://www.docker.com/)
- [An Accredible account](https://www.accredible.com/)

#### Step 1: Initial installation

Run `docker-compose.yml` without any plugins for the first time to successfully complete initial installation.

```
docker-compose up -d
```

If the initial installation is successfully completed, `==> ** Moodle setup finished! **` will be displayed in the docker log and you will be able to access the moodle instance at `http://127.0.0.1:8080`.

After the installation, you can stop the containers to re-run them with the Accredible plugin.

```
docker-compose down
```

#### Step 2: Run Moodle with the Accredible plugin

Run the Moodle instance with the Accredible plugin in your local repo.

```
docker-compose -f docker-compose.yml -f docker-compose.plugin.yml up -d
```

If you are using your Accredible account in the production, you need to set an empty value in `ACCREDIBLE_DEV_API_ENDPOINT` in `docker-compose.plugin.yml`. Otherwise, `http://127.0.0.1:3000/v1/` is used for the API calls.

#### /opt/bitnami/moodle/config.php: No such file or directory

The following error is raised if the moodle service has not completed the initial installation:

```
moodle_1      | grep: /opt/bitnami/moodle/config.php: No such file or directory
```

Please make sure if the initial installation has been completed.

If the error keeps happening, it would be better to clear the containers with the following commands:

```
docker-compose down -v
```

and set it up again from the beginning.

### Moodle instance

You can access the Moodle instance at `http://127.0.0.1:8080` and log into the admin page with:

```
MOODLE_USERNAME: user
MOODLE_PASSWORD: bitnami
```

### phpMyAdmin

You can access the phpMyAdmin at `http://127.0.0.1:8081` and log into it with:

```
MOODLE_DATABASE_USER : bn_moodle
MOODLE_DATABASE_PASSWORD: (No password)
```

### Environment variables

You can find available environment variables on [README.md](https://github.com/bitnami/bitnami-docker-moodle) of the original docker-compose repository from bitnami.

### Step debugging with Xdebug

You can step through the plugin's PHP line by line (breakpoints, call stack, variable
inspection) with [Xdebug](https://xdebug.org/). The instructions below are for the
[moodle-docker](https://github.com/moodlehq/moodle-docker) dev environment, where this
repo is bind-mounted into the webserver container at `/var/www/html/mod/accredible`.

#### 1. Enable Xdebug in the container

The `moodlehq/moodle-php-apache` image does not ship Xdebug, so it has to be compiled
into the running container:

```
scripts/xdebug.sh enable
```

The script installs Xdebug with PECL, writes the debug settings, restarts the webserver
and prints the resulting configuration. Other subcommands:

```
scripts/xdebug.sh status     # is Xdebug loaded, and with which settings?
scripts/xdebug.sh disable    # turn it off without uninstalling
```

The container is auto-detected; override it with `WEBSERVER_CONTAINER=<name>` if you run
more than one Moodle stack. `XDEBUG_PORT` (default `9003`) and `XDEBUG_IDEKEY` (default
`PHPSTORM`) can be overridden the same way.

> **Note:** the install lives in the container's writable layer. It survives
> `docker restart`, but is lost when the container is recreated (`docker compose down`,
> an image pull, etc). Just run `scripts/xdebug.sh enable` again.

The settings written are:

```
xdebug.mode = debug
xdebug.client_host = host.docker.internal
xdebug.client_port = 9003
xdebug.start_with_request = trigger
xdebug.idekey = PHPSTORM
```

`start_with_request = trigger` means only requests that explicitly ask for it are
debugged, so ordinary page loads are not slowed down or left hanging when the IDE is not
listening.

#### 2. Configure your IDE

**PhpStorm**

1. *Settings → PHP → Debug*: set **Debug port** to `9003`.
2. *Settings → PHP → Servers*: add a server named `moodle-docker`, host `localhost`,
   port `8000`, debugger *Xdebug*, tick **Use path mappings** and map:

   | Local path                        | Server path                      |
   | --------------------------------- | -------------------------------- |
   | your `moodle-docker/moodle` clone  | `/var/www/html`                  |
   | this repo                         | `/var/www/html/mod/accredible`   |

   The second mapping is easy to miss — without it, breakpoints in `lib.php`,
   `locallib.php` or `classes/**` will never be hit.
3. Click the phone icon (**Start Listening for PHP Debug Connections**).

**VS Code**

1. Install the [PHP Debug](https://marketplace.visualstudio.com/items?itemName=xdebug.php-debug)
   extension (`xdebug.php-debug`).
2. This repo ships a ready-made `.vscode/launch.json` with two configurations:

   - **Listen for Xdebug (plugin + core)** — breakpoints bind in this repo *and* in
     Moodle core. It assumes your moodle-docker checkout sits at
     `../moodle-dev/moodle-docker` relative to this repo; adjust the `/var/www/html`
     mapping if yours is elsewhere.
   - **Listen for Xdebug (plugin only)** — use this if you have not cloned Moodle core
     locally. Breakpoints outside this repo will not bind.

3. Open the **Run and Debug** panel (`⇧⌘D`), pick a configuration and press `F5`. The
   status bar turns orange while VS Code is listening on port 9003.

The `/var/www/html/mod/accredible` → `${workspaceFolder}` mapping is the important one —
without it, breakpoints in `lib.php`, `locallib.php` or `classes/**` will never be hit.

#### 3. Trigger a debug session

Set a breakpoint, make sure the IDE is listening, then:

- **Browser** — append `?XDEBUG_TRIGGER=1` to the URL, or install the
  [Xdebug helper](https://github.com/BrianGilbert/xdebug-helper-for-chrome) browser
  extension and toggle it on. The extension is the easier option for AJAX endpoints,
  since its cookie is sent with every request automatically.
- **CLI scripts / cron**:

  ```
  docker exec -e XDEBUG_TRIGGER=1 -e PHP_IDE_CONFIG=serverName=moodle-docker \
    moodle-docker-webserver-1 php admin/cli/cron.php
  ```

- **PHPUnit** — same environment variables in front of the `phpunit` command:

  ```
  docker exec -e XDEBUG_TRIGGER=1 -e PHP_IDE_CONFIG=serverName=moodle-docker \
    moodle-docker-webserver-1 php admin/tool/phpunit/cli/util.php --run mod/accredible/tests
  ```

#### Troubleshooting

- **Breakpoints are grey / never hit** — the path mapping for
  `/var/www/html/mod/accredible` is missing or wrong.
- **No connection at all** — check the container can reach the host. On Docker Desktop
  for Mac and Windows `host.docker.internal` resolves automatically; on plain Linux use
  the host IP or add `extra_hosts: ["host.docker.internal:host-gateway"]` to the
  webserver service. Also make sure port `9003` is not blocked by a firewall.
- **Requests hang with no debugger attached** — something set
  `xdebug.start_with_request = yes`. Reset it with `scripts/xdebug.sh disable` and
  re-enable.

#### Without a debugger

For quick tracing you often do not need to step at all. Set the following in the
Moodle `config.php`:

```php
$CFG->debug = (E_ALL | E_STRICT);
$CFG->debugdisplay = 1;
```

and write to the log with `error_log(print_r($value, true));`, which shows up in
`docker logs -f moodle-docker-webserver-1`.

### Test

This plugin uses [PHPUnit](https://docs.moodle.org/dev/PHPUnit) for the unit tests.

Please refer to [Writing PHPUnit tests](https://docs.moodle.org/dev/Writing_PHPUnit_tests) for more deitals about how to write unit tests.

#### Setup PHPUnit

After running Moodle with the Accredible plugin, log in to the Moodle container.

```
docker exec -it moodle-mod_accredible-moodle-1 bash
```

Add the following lines to `config.php`.

```
vim /bitnami/moodle/config.php
```

```php
// PHPUnit
$CFG->phpunit_prefix = 'phpu_';
$CFG->phpunit_dataroot = '/bitnami/phpu_moodledata';
$CFG->phpunit_dbtype    = 'mariadb';
$CFG->phpunit_dblibrary = 'native';
$CFG->phpunit_dbhost    = 'mariadb';
$CFG->phpunit_dbname    = 'test';
$CFG->phpunit_dbuser    = 'bn_moodle';
$CFG->phpunit_dbpass    = '';
```

Initialise the test environment using the following command.

```
php /bitnami/moodle/admin/tool/phpunit/cli/init.php
```

#### Run tests

Log in to the Moodle container.

```
docker exec -it moodle-mod_accredible-moodle-1 bash
```

Run unit tests of this plugin using the following command.

```
cd /bitnami/moodle
vendor/bin/phpunit --testsuite mod_accredible_testsuite
```

Run unit tests for a single test class.

```
vendor/bin/phpunit --filter mod_accredible_xxx_testcase
```

If you encounter the following error while running tests,
```
Error in bootstrap script: 
cache_exception: cache/Invalid cache configuration file 
$a contents:
```
Run the script below to clear the `test` database and run the `init.php`  command above to initialize the test environment again.
```
php /bitnami/moodle/admin/tool/phpunit/cli/util.php --drop
```

### Coding style

This plugin is trying to be consistent and follow the recommendations according to [the Moodle coding style](http://docs.moodle.org/dev/Coding_style).

### Code checker setup

Coding style is checked with [PHP_CodeSniffer](https://github.com/PHPCSStandards/PHP_CodeSniffer) (PHPCS) using the
[`moodle` standard from `moodlehq/moodle-cs`](https://github.com/moodlehq/moodle-cs). This is the same standard the CI
workflow applies via `moodle-plugin-ci phpcs`.

Install both with a single Composer global requirement — `moodle-cs` depends on PHPCS, so it is pulled in for you:

```
composer global config allow-plugins.dealerdirect/phpcodesniffer-composer-installer true
composer global require moodlehq/moodle-cs
```

Make sure Composer's global `bin` directory is on your `PATH`:

```
export PATH="$(composer global config home)/vendor/bin:$PATH"
```

No manual `installed_paths` configuration is needed — `moodle-cs` ships the
`dealerdirect/phpcodesniffer-composer-installer` plugin, which registers the standard with PHPCS on install.

Verify the setup:

```
phpcs --version
phpcs -i
```

Confirm that `moodle` is listed in the installed coding standards.

### Run Code checker

Check the whole plugin:

```
phpcs --standard=moodle --extensions=php '--ignore=*/node_modules/*,*/vendor/*' .
```

The quotes around `--ignore` are required in zsh, which otherwise tries to expand the globs itself and fails with
`no matches found`.

Check a specific directory or file:

```
phpcs --standard=moodle [FILE_PATH]
```

Other useful options:

```
phpcs --standard=moodle --warning-severity=0 [FILE_PATH]   # errors only
phpcbf --standard=moodle [FILE_PATH]                       # auto-fix what can be fixed
```

CI runs `moodle-plugin-ci phpcs --max-warnings 0`, so warnings fail the build just like errors. The plugin should
report zero of both before you open a PR.
