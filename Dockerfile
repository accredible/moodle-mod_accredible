# Use the base image for Moodle
FROM docker.io/bitnami/moodle:4.3.5

# Install dependencies for PHP 8.0
RUN apt-get update && apt-get install -y software-properties-common wget lsb-release gnupg2

# Add the Sury PHP repository
RUN wget -qO - https://packages.sury.org/php/apt.gpg | apt-key add -
RUN echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/php.list

# Update package list
RUN apt-get update

# Install PHP 8.0 and necessary extensions
RUN apt-get install -y php8.0 php8.0-cli php8.0-fpm php8.0-mysql php8.0-xml php8.0-mbstring php8.0-zip php8.0-gd php8.0-curl php8.0-intl php8.0-soap php8.0-xmlrpc php8.0-ldap php8.0-apcu

# Remove PHP 7.4
RUN apt-get remove -y php7.4 php7.4-cli php7.4-fpm php7.4-mysql php7.4-xml php7.4-mbstring php7.4-zip php7.4-gd php7.4-curl php7.4-intl php7.4-soap php7.4-xmlrpc php7.4-ldap php7.4-apcu

# Clean up
RUN apt-get autoremove -y && apt-get clean

# Update the PHP-FPM configuration to use the correct PHP version
RUN ln -sf /usr/sbin/php-fpm8.0 /usr/sbin/php-fpm

# Install 'vim'
RUN install_packages vim

# Make a directory for PHPUnit data
RUN mkdir /bitnami/phpu_moodledata
