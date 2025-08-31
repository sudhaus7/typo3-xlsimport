# XLS Importer

[![Latest Stable Version](https://poser.pugx.org/sudhaus7/xlsimport/v/stable.svg)](https://extensions.typo3.org/extension/xlsimport/)
[![TYPO3 13](https://img.shields.io/badge/TYPO3-13-green.svg)](https://get.typo3.org/version/13)
[![Total Downloads](https://poser.pugx.org/sudhaus7/logformatter/d/total.svg)](https://packagist.org/packages/sudhaus7/xlsimport)
[![Monthly Downloads](https://poser.pugx.org/sudhaus7/xlsimport/d/monthly)](https://packagist.org/packages/sudhaus7/logformatter)

## TCA driven import Extension for Spreadsheets

### What does it do?

This extension helps to import data from Spreadsheets into TYPO3.

### How does it work?

This extension provides a new backend module in which you can use every TCA
configured table for importing data from Excel.

When uploading the spreadsheet, you can select the table you want to import data
to.

After the upload, you will get a table with the data of the first worksheet.
In the header, you can select your field to import to,
so you don't have to respect a specific order of columns in your spreadsheet.

Every line can be removed, so you import only the data you want to.

### Configuration

The module can be configured by TSconfig. The following snippet has to be added
to your Page TS Configuration.

This is the Default Setting:
```
module.tx_xlsimport {
    settings {
        allowedTables = tt_address
    }
}
```
which you can either override, or extend in this fashion:

```
module.tx_xlsimport {
    settings {
        allowedTables := addItems(my_table_configured_in_TCA)
    }
}
```

*Important*: you have to specify the TABLENAME of an extension you want to
import to, not the extension name. So for example, if you want to import data for
the news extension (tx_news) you have to add the tablename
`tx_news_domain_model_news`. Respectfully, if you want to import tx_news's Tags,
you have to add the tablename `tx_news_domain_model_tag` to the list.

The extension in itself does not maintain relations out of the box.

### Limitations

When a non-admin user is using the tool, the folder the import is made to has to
either be inside a site-setup with defined languages OR the sys_language_id
(0,1,2,3) has to be part of the imported data. If these requisits are not met,
the tool will report a successful import, while no data has been imported. We
hope to address this in a future update.

### TODO:
- add more Events/Signalslots for datamanipulation
- add support for multiple sheets

### FUTURE: (hit me up if you are willing to help funding)
- support for related data as far as it is modelled in the TCA
- support for import-presets or templates for recurring import tasks

### Local development

#### Local development environment with `ddev`

This extension repository includes a `ddev` instance configuration matching the development
requirements and is setup to provide and easy and simple development experience for people,
which wants to contribute or play around.

That means, thatn on a simple `ddev start` the environment is checked and the TYPO3 instance
configured (setup) against ddev with a generic admin user along with creating pages tree for
the backend using `typo3/cms-styleguide` and the included generator.

**Start**

```shell
ddev start
```

**Full reset (destroy)**

```shell
ddev stop -ROU \
  && git clean -xdf -e '.idea'
```

**Recreate instance**

```shell
ddev stop -ROU \
  && git clean -xdf -e '.idea' \
  && ddev start
```

To simplify the setup, a generic admin user is created:

| type | username | password        | email                |
|------|----------|-----------------|----------------------|
| BE   | john-doe | John-Doe-1701D. | john.doe@example.com |

with following urls:

| url                                                  | command                         | description                  |
|------------------------------------------------------|---------------------------------|------------------------------|
| https://typo3-xlsimport.ddev.site/typo3/             | ddev launch /typo3/             | Open the TYPO3 backend       |
| https://typo3-xlsimport.ddev.site/styleguide-demo-1/ | ddev launch /styleguide-demo-1/ | Open the styleguide frontend |

### Changes in v5.0

* removed TypoScript support, as TypoScript is frontend related
  * use Page TSconfig instead
  * Configuration is the same as in TypoScript
  * Or configure via Extension Configuration
* Rework for removing Extbase dependencies in Controller
  * No changes needed
