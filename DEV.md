# How to Setup a Development Environment

Create or modify the following file to add the dev folder to the ddev container:
`.ddev/docker-compose.mounts.yaml`
```
services:
  web:
    volumes:
      - "$HOME/devdrive/craft-meierlabs-table:/home/craft-meierlabs-table"
```

Run `ddev restart` to apply the changes.

Modify the `composer.json` to add the local path:
```
"repositories": [
    {
        "type": "path",
        "url": "/home/craft-meierlabs-table"
    }
]
```

and change the require section to:
```
"require": {
    "soerenmeier/meierlabs-table": "dev-main",
}
```

Then run `ddev composer update soerenmeier/meierlabs-table` to install the local version of the plugin.

## Javascript
If you want to modify the javascript code run `npm install` and then `npm run watch` to automatically build
the js files on changes.
