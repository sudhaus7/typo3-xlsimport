# XLS Importer

## TCA driven import Script for Spreadsheets

### What does it do?

This extension helps to import data from Spreadsheets into TYPO3.

###How does it work?

This extension provides a new backend module. You can configure every TCA configured 
table for import.

By default, the table tt_address can be imported. During uploading the spreadsheet, you can select
the table, you want to import to.

After upload, you will get a table with the data of the first worksheet. At the header
you can select your field to import to, so you don't have to respect a specific order in your spreadsheet.

Every line could be removed, so you import only the data, you want to.

### Configuration

The module could be configured by TypoScript.

```
module.tx_xlsimport {
	settings {
		allowdeTables = tt_address
	}
}
```

Just add

```
module.tx_xlsimport {
	settings {
		allowedTables := addItems(my_table_configured_in_TCA)
	}
}
```

