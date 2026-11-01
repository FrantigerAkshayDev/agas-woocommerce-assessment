# AGAS Design Studio
### WooCommerce Developer Skills Assessment

AGAS Design Studio is a custom WordPress and WooCommerce
product-personalization application.

The project demonstrates how customers can select a
ready-made design, customize it in an online editor, and
place an order for a printed product.

The design tool was developed as a custom WordPress plugin
without using paid product-designer plugins.

This is an assessment prototype rather than a
production-ready printing platform.

## Project components

- **Custom plugin:** `plugin/agas-design-studio/`
- **Custom child theme:** `theme/agas-printworks-child/`
- **Local environment:** `compose.yaml`

The application supports three demonstration products:

- Outdoor Vinyl Banner
- Custom Flag
- Yard Sign

## Main features

The project includes:

- Product-specific template galleries
- Search and category filtering
- Ready-made designs and blank canvas options
- Interactive design editing using Fabric.js
- Text, graphics, image uploads and object controls
- Front and back artwork editing
- Product dimensions and finishing options
- Server-side price calculation
- WooCommerce cart and checkout integration
- Saved customer designs
- Administrator template management
- Order-linked artwork and print-file exports
- Product mockup previews

Individual features and their testing limitations
are documented in the plugin README.

## Technology

The application uses:

- WordPress and PHP
- WooCommerce
- Fabric.js 5
- JavaScript, HTML and CSS
- MariaDB
- Docker Compose
- Storefront as the free parent WordPress theme
- AGAS Printworks as the custom child theme

The editor is implemented inside the plugin rather
than being coupled to the storefront theme.

## Local installation

### Requirements

- Docker Desktop with Docker Compose
- A modern web browser
- Internet access for externally loaded editor libraries

### Setup

1. Clone this repository.
2. Open the repository directory.
3. Configure the database password environment variables
   referenced by `compose.yaml` in a local `.env` file.
4. Start the local environment:

   docker compose up -d

5. Open http://localhost:8099.
6. Complete the WordPress installation.
7. Install and activate WooCommerce.
8. Install the free Storefront theme.
9. Install and activate the AGAS Printworks child theme.
10. Create the three demonstration WooCommerce products
    using the exact product names listed above.
11. Activate the AGAS Design Studio plugin and complete
    its template initialization as described in the
    plugin README.
12. Enable Cash on Delivery for test purchases.

WordPress database records, uploaded media and order
history are not included in the source repository.

The deployed demonstration site is supplied separately.

## How the application works

### Customer workflow

1. Open a designable product and its template gallery.
2. Select a template or start with an empty design.
3. Customize the artwork using the online editor.
4. Choose dimensions, sides and finishing options.
5. Save the design and continue to the cart.
6. Review the artwork and complete checkout.

### Administrator workflow

Administrators can manage design templates and review
customized WooCommerce orders.

The print export interface allows administrators to
generate available PDF and PNG artwork for order sides.

## Architecture and data storage

The plugin uses WordPress custom post types for templates
and customer designs.

Templates are represented using JSON-based canvas data,
with metadata for product and category assignments.

Saved designs store Fabric.js canvas JSON, printing
options, product information and preview data.

WooCommerce order items retain references to the saved
designs and their printing options.

Pricing is recalculated on the server rather than
trusting a price submitted by the browser.

## Print-ready exports

The administrative export interface generates PDFs
and PNGs from saved order artwork.

The PDF workflow attempts vector output using browser
libraries. When that is unavailable, a raster PDF
fallback can be used.

The PNG workflow targets approximately 150 DPI at
the selected physical dimensions.

Exports are generated on demand. They are not retained
as permanent files in server-side storage.

Print outputs require preflight verification before
real manufacturing use.

## Testing

The plugin includes test scripts under:

`plugin/agas-design-studio/tools/`

These cover selected pricing, cart, editor, template
and interface behaviors.

Automated tests do not replace full WooCommerce
browser acceptance testing.

The accompanying screen recording demonstrates
the assessment workflows on the prepared demo store.

## Known limitations

This is an assessment implementation with several
areas requiring additional production hardening:

- Print exports require independent preflight checks.
- PDF vector fidelity can vary with fonts and libraries.
- Large raster exports can be limited by browser memory.
- Original SVG uploads are not preserved separately
  in the same way as embedded PNG/JPG artwork.
- Generated print files are not permanently stored.
- Mobile, accessibility and cross-browser testing
  require further coverage.
- The Git repository does not contain the populated
  WordPress database or customer order records.

See the plugin's README and version-specific update
notes for further implementation details.

## What I would improve with five more days

I would prioritize comprehensive browser and mobile
testing, accessibility improvements, durable print-file
storage, automated print preflight checks, image-quality
warnings, and stronger deployment automation.

I would also improve the administrative order workflow
and add a reliable background export pipeline for
larger print jobs.

## ERP integration direction

A future integration with an ERP such as Priority
would use a queued order-processing service.

Validated order information, product details, printing
options and securely stored artwork references would
be sent through authenticated APIs.

Retries, status synchronization and audit logging
would make the integration more reliable.

## Assessment submission

The submission consists of:

1. A separately hosted WooCommerce demo store
2. This Git repository containing the source code
3. A screen recording demonstrating the application

Reviewer administrator credentials are shared
separately and are not included in this repository.
