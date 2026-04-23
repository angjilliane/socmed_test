
copy default.settings.php to settings.php
update db config

composer install       
vendor/bin/drush si  -y
vendor/bin/drush cset system.site uuid 5a96647d-b702-42a4-8cad-601a9c70ec42 -y
vendor/bin/drush entity:delete shortcut_set default -y
vendor/bin/drush cim -y
vendor/bin/drush cr


update admin password
vendor/bin/drush user:password admin "admin"

vendor/bin/drush sql:query "TRUNCATE flood;"
