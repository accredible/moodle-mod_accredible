# Use the base image for Moodle
FROM docker.io/bitnami/moodle:4.5.4

# Install development tools
RUN install_packages vim

# Create directory for PHPUnit data with proper permissions
RUN mkdir -p /bitnami/phpu_moodledata && \
    chown -R 1001:1001 /bitnami/phpu_moodledata

# Install PHPUnit if needed for testing
RUN install_packages phpunit

# Create Apache configuration directory with proper permissions
RUN mkdir -p /bitnami/apache/conf && chown -R 1001:1001 /bitnami/apache/conf
