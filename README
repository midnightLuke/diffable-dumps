# Diffable Dumps

Drupal database dumps aren't easy to diff, this makes them a little better by
parsing the dumps, unserializing serialized fields and then re-serializing them
as pretty-printed JSON files.

Once you've got some files and transformed them you can diff them to get an idea
what you've changed in the database.

## Why?

I don't even know man, I don't even know...

But for real on legacy projects (see: they don't use features) it can be hard to
track what is changing, this utility can make it easier to figure out the
difference between two databases.  Drupal has a bad habit of serializing data in
the database, this just re-serializes it into a format that is easier to diff
because it has a bunch of newlines in it.

## Requirements

Dumps so far come from Sequel Pro and in XML only, you'll also need composer.

## Installation

```
$ git clone https://github.com/midnightLuke/diffable-dumps
$ cd diffable-dumps
$ composer install
```

## Usage

```
$ php app.php transform:data [source-dir] [output-dir]
```

## Caveats

This is dumb, use features on your projects.
