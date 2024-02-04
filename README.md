# Global Giving Search

The project uses the Global Giving API.  It is not formally associated with https://www.globalgiving.org/ except for using the API https://www.globalgiving.org/api/

## Developers

To install for local development, fork this repository and install it, e.g.


```bash
git clone git@github.com:survos-sites/global-giving gg && cd gg
# for developers 
symfony proxy:domain:attach gg

echo "DATABASE_URL=sqlite:///%kernel.project_dir%/var/data.db" > .env.local
composer install
bin/console doctrine:schema:update --force --complete
# add GLOBAL_GIVING_API_KEY to .env.local
# cp ~/g/sites/gg/.env.local .
bin/console app:load-data

symfony server:start -d
symfony open:local--path=/api/meili/orgs



```

Get an API key at https://www.globalgiving.org/dy/v2/user/api/ and add it to your .env.local

```
GLOBAL_GIVING_API_KEY=your-api-key
```




