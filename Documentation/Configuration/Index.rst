.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt



.. _configuration:

Configuration
-------------

This section describes the configuration options for XLS importer.

Adding configuration needs a TCA defined table.


.. _module_setup:

Module Setup
^^^^^^^^^^^^

Add the TypoScript setup as usual to your sitepackage
extension configuration file
:file:`mysitepackage/Configuration/TypoScript/setup.typoscript`::

   module.tx_xlsimport {
       settings {
           allowedTables := addToList(tt_address)
       }
   }

.. note::
   If you have problems overwriting the configuration in your sitepackage,
   make sure to select "Include before all static templates if root flag is
   set" from the "Static Template Files from TYPO3 Extensions" dropdown
   list in your TypoScript template.

Every TCA defined table could be used. Field names are taken by locallang
files, so localization is done.
