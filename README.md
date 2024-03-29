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
echo "GLOBAL_GIVING_API_KEY=b82dd104-3825-4db8-8c54-8fbe357bf9b4" >> .env.local
bin/console app:load-data

symfony server:start -d
symfony open:local --path=/api/meili/orgs



```

Configure the auth package:

https://github.com/survos/SurvosAuthBundle/blob/bcf32095dc483dcb5fb8d4cac8632b132b40621c/.install/symfony/config/routes/survos_auth.yaml#L1-L3

Get an API key at https://www.globalgiving.org/dy/v2/user/api/ and add it to your .env.local

```
GLOBAL_GIVING_API_KEY=your-api-key
```




