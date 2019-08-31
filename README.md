# Source Snap
Automatically create colorful 'code snippet' Featured Images.

## Installation
Make sure Imagick's enabled and able to read PDF files. Unpack in `wp-content/plugins`. Run `composer install`. Optionally, add your TinyPNG API account's details.

## Usage
Create/edit a blog post. Add some code to a `source_snap_snippet` custom field and publish/update! The post's Featured Image will be set to the source code 'snapshot'. If the wrong language is somehow detected, try explicitly adding it as a `source_snap_lang` custom field.
