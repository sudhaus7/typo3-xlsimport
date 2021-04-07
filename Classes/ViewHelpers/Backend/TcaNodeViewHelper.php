<?php

declare(strict_types=1);

namespace SUDHAUS7\Xlsimport\ViewHelpers\Backend;

use TYPO3\CMS\Backend\Form\NodeFactory;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class TcaNodeViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    protected $escapeChildren = false;

    public function initializeArguments()
    {
        $this->registerArgument('config', 'array', 'The TCA configuration', true);
        $this->registerArgument('name', 'string', 'The form name field', true);
        $this->registerArgument('table', 'string', 'The table', true);
        $this->registerArgument('page', 'integer', 'The page UID', true);
    }

    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        $nodeFactory = GeneralUtility::makeInstance(NodeFactory::class);
        $tcaConfig = $arguments['config'];

        if ($tcaConfig['foreign_table'] || ($tcaConfig['allowed'] && $tcaConfig['internal_type'] == 'db')) {
            $table = $tcaConfig['allowed'] ?? $tcaConfig['foreign_table'];
            $db = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
            $statement = $db
                ->select(...[
                    $GLOBALS['TCA'][$table]['ctrl']['label'],
                    'uid',
                ])
                ->from($table);
            if ($tcaConfig['foreign_table_where']) {
                $statement->andWhere(str_replace('AND', '', $tcaConfig['foreign_table_where']));
            }
            $tcaConfig['items'] = array_merge_recursive($tcaConfig['items'] ?? [], $statement->execute()->fetchAllNumeric());
        }
        $data = [
            'renderType' => $arguments['config']['renderType'] ?? '',
            'inlineStructure' => [],
            'parameterArray' => [
                'itemFormElID' => sprintf('data_%s_%s_%s', $arguments['table'], $arguments['page'], $arguments['name']),
                'itemFormElValue' => '',
                'fieldConf' => [
                    'config' => $tcaConfig
                ],
                'itemFormElName' => sprintf('data[%s][%s][%s]', $arguments['table'], $arguments['page'], $arguments['name']),
                'fieldChangeFunc' => [
                    'TBE_EDITOR_fieldChanged' => sprintf(
                        'TBE_EDITOR.fieldChanged(\'%2$s\',%3$d,\'%1$s\', \'data[%2$s][%3$d][%1$s]\')',
                        $arguments['name'],
                        $arguments['table'],
                        $arguments['page']
                    )
                ],
            ]
        ];
        $result = $nodeFactory->create($data)->render();

        return $result['html'];
    }
}