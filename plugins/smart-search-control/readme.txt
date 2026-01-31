#  Smart Search Control

Contributors: LDNinjas, farooqabdullah
Tags: search control, analytics, woocommerce variation search, wordpress advanced search, post based search
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.0.3
Requires PHP: 7.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A powerful plugin to enhance search functionality of your WordPress and WooCommerce sites with smart controls and customization.

## Description

Enhance the search functionality of your WordPress and WooCommerce site with the **Smart Search Control** Plugin. This plugin adds a powerful and intelligent search engine to your site without replacing the default WordPress search. It allows you to display customizable search forms anywhere using shortcodes, offering users more accurate and relevant results where needed.

[youtube https://www.youtube.com/watch?v=8nKal2-a-4g]

## Key Features

- **Improved Accuracy:** Delivers more relevant search results by analyzing content beyond titles and excerpts.  
- **Customizable Search Algorithm:** Fine-tune the search algorithm to prioritize specific content types or fields.  
- **Live Search Suggestions:** Provides real-time search suggestions as users type, improving the search experience.  
- **Custom Fields Support:** Indexes and searches within custom fields created by plugins like Advanced Custom Fields (ACF).  
- **WooCommerce Compatibility:** Optimizes search for WooCommerce products, including product attributes and variations.  
- **Search Analytics:** Track user search queries to gain insights into what your visitors are looking for.  
- **Easy Installation and Setup:** Simple installation and configuration process.

## Installation

1. **WordPress Plugin Repository:**  
    - Go to your WordPress admin panel.  
    - Navigate to "Plugins" > "Add New".  
    - Search for "Smart Search Control".  
    - Click "Install Now" and then "Activate".

2. **Manual Installation:**  
    - Download the plugin ZIP file.  
    - In your WordPress admin panel, go to "Plugins" > "Add New".  
    - Click "Upload Plugin" and select the ZIP file.  
    - Click "Install Now" and then "Activate".

## Configuration

1. Navigate to the **Smart Search Control** settings page in your WordPress admin panel.  
2. Configure the search settings based on your preferences, including:  
    - Selecting which content types to include in the search.  
    - Prioritizing specific fields or content for more accurate results.  
    - Adding a custom **placeholder, CSS ID,** and **CSS class** for styling and customization.  
3. Select the page where you want to display the search results from the available options or create a new one for this purpose.
4. Click **Save** to apply your configuration.

## Usage

To use the smart_search_control shortcode, simply insert the following shortcode into your post, page, or widget and provide an existing id associated with a configured search control. This will display the smart search form on that page.

**Example**
```php
[smart_search_control id="123"]
```
In this example, 123 should be replaced with the ID of an existing smart search setup. Once added, the corresponding search form will appear on the page and be fully functional.


## Frequently Asked Questions

- **Is the plugin performance optimized?**  
    - Yes, the plugin is designed to be efficient and optimized for performance.

- **Is this plugin compatible with WooCommerce?**  
    - Yes, the plugin enhances search for WooCommerce products.

- **Can I customize the search results page?**  
    - The plugin improves the search results, and you can further customize the appearance using your theme's templates.

- **Does it support custom post types?**  
    - Yes, the plugin supports custom post types.

## Troubleshooting

- If you encounter any issues, please refer to the [plugin documentation](https://ldninjas.com/docs/smartsearch-control/) or [contact our support team](https://ldninjas.com/contact-us/).

## Support

For support, please visit our support forums or contact us through [our website](https://ldninjas.com).

## Changelog

- **1.0.3** 
    - New: Added admin notification if search result page is not selected. 
    - Fix: Fixed content override issue with search shortcode.
    - Fix: CSS Tweaks

- **1.0.2**  
    - New: Added option to display search results in list or grid view.
    - New: Implemented semantic UI for the results.
    - Fix: Fix pagination issues when no records are found.
    - Fix: Made the plugin compatible with WordPress 6.9
    - Fix: Made the plugin compatible with PHP 8.0


- **1.0.1**  
    - New: Remove php 5.4 support for stability
    - New: Added filter to update per page result counts.
    - New: Added filter to update count on search suggestions.
    - New: Added action hook to add new column to the admin table that displays all the created shortcodes.
    - New: Added action hook to add new fields to the search form.
    - New: Added action hook to add custom content to the settings page.
 
- **1.0.0**  
    - Initial release  