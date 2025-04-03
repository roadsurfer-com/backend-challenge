# Microservice base template

The Roadsurfer base template for creating new (micro) services. It holds only the bare minimum requirements to have
a skeleton like application.

## Adjustments for new projects

The template itself is capable to be able to run. Every project needs to adjust the configuration to it's needs.
Parts that need to be adjusted are:

### Docker
  * Change `server_name` to desired value

### Deployment

* Change values in `Chart.yaml`
* Create `values_{APP_ENV}.yaml` files for all existing environments
* Replace the placeholders in the deployment files

Caution: The deployment has a lot of configuration values which might not be suitable for the projects needs.


## Installation

### Creating the containers

```bash
docker-compose up # optionally -d --build --remove-orphans
```

### Installing the dependencies

```bash
docker-compose exec php composer install
```
