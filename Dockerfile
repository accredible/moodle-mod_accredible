FROM docker.io/bitnami/moodle:4.3.4

# Install 'vim'
RUN install_packages vim

# Make a directory for PHPUnit data
RUN mkdir /bitnami/phpu_moodledata
