# Use official PHP image from DockerHub
FROM php:8.1-apache

# Set the working directory
WORKDIR /var/www/html

# Copy the contents of the current directory to the container's working directory
COPY ./public /var/www/html/

# Enable Apache rewrite (if you want to use .htaccess)
RUN a2enmod rewrite

# Expose port 80 for the web service
EXPOSE 80

# Start Apache server
CMD ["apache2-foreground"]
