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

.. _extension_setup:

Extension Setup
^^^^^^^^^^^^^^^

Go to `Install Tool > Settings > Extension Settings` and add your tables as
comma separated list.

.. _module_setup:

Module Setup
^^^^^^^^^^^^

Add the TypoScript setup as usual to your sitepackage
extension configuration file
:file:`mysitepackage/Configuration/TSConfig/PageTS/tx_xlsimport.tsconfig`::

   module.tx_xlsimport {
       settings {
           allowedTables := addToList(tt_address)
       }
   }


Every TCA defined table could be used. Field names are taken by locallang
files, so localization is done.
