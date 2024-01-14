# XLS Importer

[![Latest Stable Version](https://poser.pugx.org/sudhaus7/xlsimport/v/stable.svg)](https://extensions.typo3.org/extension/xlsimport/)
[![TYPO3 11](https://img.shields.io/badge/TYPO3-11-orange.svg)](https://get.typo3.org/version/11)
[![Total Downloads](https://poser.pugx.org/sudhaus7/logformatter/d/total.svg)](https://packagist.org/packages/sudhaus7/xlsimport)
[![Monthly Downloads](https://poser.pugx.org/sudhaus7/xlsimport/d/monthly)](https://packagist.org/packages/sudhaus7/logformatter)

## TCA driven import Script for Spreadsheets

### What does it do?

This extension helps to import data from Spreadsheets into TYPO3.

### How does it work?

This extension provides a new backend module in which you can use every TCA configured
table for importing data from an excel.

In the default setup the table tt_address can be imported out of the box.

When uploading the spreadsheet, you can select
the table you want to import data to.

After the upload, you will get a table with the data of the first worksheet. In the header
you can select your field to import to, so you don't have to respect a specific order of columns in your spreadsheet.

Every line can be removed, so you import only the data you want to.

### Configuration

The module can be configured by TypoScript. The following snippet has to be added to your Typoscript Template (frontend).

This is the Default Setting:
```
module.tx_xlsimport {
    settings {
        allowedTables = tt_address
        # {storageUid}:{folderIdentifier} eg: 1:user_upload/data/import
        uploadFolder = 1:user_upload/tx_xlsimport
        # replace | rename | cancel
        duplicationBehavior = rename
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

*Important*: you have to specify the TABLENAME of an extension you want to import to, not the extensionname. So for example if you want to import data for the news extension (tx_news) you have to add the tablename `tx_news_domain_model_news`. Respectfully, if you want to import tx_news's Tags, you have to add the tablename `tx_news_domain_model_tag` to the list.

The extension in itself does not maintain relations out of the box.

### Limitations

When a non-admin user is using the tool, the folder the import is made to has to either be inside a site-setup with defined languages OR the sys_language_id (0,1,2,3) has to be part of the imported data. If these requisits are not met, the tool will report a successful import, while no data has been imported. We hope to address this in a future update.

### TODO:
- add Events/Signalslots for datamanipulation
- add support for multiple sheets

### FUTURE: (hit me up if you are willing to help funding)
- support for related data as far as it is modelled in the TCA
- support for import-presets or templates for recurring import tasks
