# Bedrock
composer install

# Sage
composer install -d ./web/app/themes/ivystreet
npm --prefix "./web/app/themes/ivystreet/" install
npm --prefix "./web/app/themes/ivystreet/" run build:production

# change all directory permissions
find . -type d -not -path "./node_modules*" -exec chmod 755 {} +
# change all file permissions
find . -type f -not -path "./node_modules*" -exec chmod 644 {} +

# cache dir for generated pdf # allow apache to write
chmod 775 ./web/app/uploads

# enable web server access to all files/folders
chgrp -R apache .