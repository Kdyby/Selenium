# BDD with Behat


## Directory layout

```
 tests/
   behat.yml
   behat/
     bootstrap.php   -- with class MyBehatContext
   features/
     Orders/
       basket.feature
       pay-card.feature
```


## Usage


To the directory with tests (e.g. `tests/`) add `behat.yml`:
```yml
default:
    paths:
        features: features    -- this is directory where your *.features files are
        bootstrap: behat      -- this is where you can have any classes, usually definition of MyBehatContext

    context:
        class: MyBehatContext
        parameters:
            sitemapDirs: "%behat.paths.sitemap%"
```
