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

Find `behat.xml` and add this definition (dunno how to do it properly atm):
```xml
	    <parameter key="behat.paths.sitemap">%behat.paths.base%/Sitemap</parameter>
        <service id="behat.context.loader.pageObject" class="Kdyby\Selenium\Behat\DefinitionLoader">
            <argument type="service" id="behat.definition.dispatcher" />
            <argument type="service" id="behat.hook.dispatcher" />
            <argument>%behat.paths.sitemap%</argument>
            <tag name="behat.context.loader" />
        </service>
```


To the directory with tests (e.g. `tests/`) add `behat.yml`:
```yml
default:
    paths:
        features: features    -- this is directory where your *.features files are
        bootstrap: behat      -- this is where you can have any classes, usually definition of MyBehatContext

    context:
        class: MyBehatContext
```
