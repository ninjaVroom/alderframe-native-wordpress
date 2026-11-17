# Alderframe Studio

Samuel Vroom

A WordPress portfolio site for a residential landscape studio. A native block theme handles the pages; a separate plugin holds the content records, queries and site-visit form.

## Case study

A garden page needed more than a photograph and a title. The content model keeps the site conditions, design brief, response and outcome in separate fields. Gardens can be grouped by their site problem and connected to the services that shaped them. Locality labels show the neighbourhood rather than a private address.

The theme uses WordPress block templates for the home page, garden archive, service pages and journal. The plugin registers dynamic blocks and gives editors one field contract for each record type. Content rules sit in the plugin, so changing the theme does not remove the garden and service records.

The site-visit form checks the postcode against the configured service area, then validates the service, budget and contact details. Accepted requests are stored as private records before an email is attempted. A submission ID is used to identify repeat submissions. CRM delivery remains a pending status and an extension hook.

The theme includes self-hosted fonts and prepared media. The Blender scripts build the landscape models used for the visual material. Seed images are labelled as illustrative in the media metadata; they are not presented as verified photographs of completed client work.

This is a portfolio demonstration. The seed command can populate a fresh WordPress installation with linked services, gardens and pages. Live SMTP delivery, a CRM worker and production hosting still need configuration. No commercial results or live client deployment are claimed here.

## Run locally with WordPress Playground

Use Node.js 22 and npm. From `dev/`:

```sh
npm ci
npm run wp
```

Open `http://127.0.0.1:9400`. The included blueprint mounts the theme and plugin, activates them and runs the seed command in the disposable Playground installation. The blueprint's admin/password values are public development defaults. Do not use them on a public WordPress server.

## Install on a separate WordPress site

Use WordPress 6.7 or later and PHP 8.1 or later. Copy the `alderframe` theme and `alderframe-site` plugin from `wp-content/` into the matching folders of a disposable WordPress installation. With WP-CLI pointed at that installation:

```sh
wp plugin activate alderframe-site
wp theme activate alderframe
wp alderframe seed
wp rewrite flush
```

The seed updates records by their source IDs. Use it on a test installation; it also updates site settings and launch content.

## Source and checks

`wp-content/plugins/alderframe-site/` contains the content model, editor blocks, query rules and form handling. `wp-content/themes/alderframe/` contains templates, styles, scripts and runtime assets. `dev/build_templates.py` regenerates the block templates. The two source scripts in `dev/blender/` require Blender.

GitHub Actions checks all shipped PHP and browser JavaScript for syntax errors. These checks do not replace WordPress runtime testing, mail delivery tests or a production accessibility review. Font license notices remain beside the font files; the theme's GPL declaration is preserved in `style.css`.

## Figma design

[View the Figma design](https://www.figma.com/design/NoaCEk53nEXkMcn6CNAVdA/Alderframe-Studio---Editable-Website-Design).
