<?php

declare(strict_types=1);

namespace SUDHAUS7\Xlsimport\ViewHelpers\Backend;

use Closure;
use TYPO3\CMS\Backend\Form\Exception;
use TYPO3\CMS\Backend\Form\NodeFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class TcaNodeViewHelper extends AbstractViewHelper
{
    /**
     * @var bool
     */
    protected $escapeOutput = false;

    /**
     * @var bool
     */
    protected $escapeChildren = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('config', 'array', 'The TCA configuration', true);
        $this->registerArgument('name', 'string', 'The form name field', true);
        $this->registerArgument('table', 'string', 'The table', true);
        $this->registerArgument('page', 'integer', 'The page UID', true);
        $this->registerArgument('as', 'string', 'The value', false, 'tcaField');
    }

    /**
     * renderStatic
     * @param array{
     *     config: array<array-key, mixed>,
     *     name: non-empty-string,
     *     table: non-empty-string,
     *     page: positive-int,
     *     as: non-empty-string
     * } $arguments
     * @return mixed
     * @throws \Doctrine\DBAL\Exception
     * @throws Exception
     */
    public static function renderStatic(
        array $arguments,
        Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ) {
        $nodeFactory = GeneralUtility::makeInstance(NodeFactory::class);
        $tcaConfig = $arguments['config'];

        $templateVariableContainer = $renderingContext->getVariableProvider();

        if ($tcaConfig['foreign_table'] || ($tcaConfig['allowed'] && $tcaConfig['internal_type'] === 'db')) {
            $table = $tcaConfig['allowed'] ?? $tcaConfig['foreign_table'];
            $db = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
            $statement = $db
                ->select(
                    $GLOBALS['TCA'][$table]['ctrl']['label'],
                    'uid'
                )
                ->from($table);
            if ($tcaConfig['foreign_table_where'] && stripos((string)$tcaConfig['foreign_table_where'], 'order by') === false) {
                $tcaConfig['foreign_table_where'] = str_replace('###CURRENT_PID###', '0', $tcaConfig['foreign_table_where']);

                if (str_starts_with(trim($tcaConfig['foreign_table_where']), 'AND')) {
                    $tcaConfig['foreign_table_where'] = ' 1=1 ' . $tcaConfig['foreign_table_where'];
                }

                $statement->andWhere($tcaConfig['foreign_table_where']);
            }
            $tcaConfig['items'] = array_merge_recursive($tcaConfig['items'] ?? [], $statement->executeQuery()->fetchAllNumeric());
        }
        $data = [
            'renderType' => $arguments['config']['renderType'] ?? $arguments['config']['type'],
            'inlineStructure' => [],
            'parameterArray' => [
                'itemFormElID' => sprintf('tx_xlsimport_web_xlsimporttxxlsimport_%s', $arguments['name']),
                'itemFormElValue' => [],
                'fieldConf' => [
                    'config' => $tcaConfig,
                ],
                'itemFormElName' => sprintf('tx_xlsimport_web_xlsimporttxxlsimport[overrides][%s]', $arguments['name']),
                'fieldChangeFunc' => [
                    'TBE_EDITOR_fieldChanged' => sprintf(
                        'TBE_EDITOR.fieldChanged(\'%2$s\',%3$d,\'%1$s\', \'tx_xlsimport_web_xlsimporttxxlsimport[overrides][%1$s]\')',
                        $arguments['name'],
                        $arguments['table'],
                        $arguments['page']
                    ),
                ],
            ],
        ];
        $result = $nodeFactory->create($data)->render();

        $templateVariableContainer->add($arguments['as'], $result);

        $output = $renderChildrenClosure();

        $templateVariableContainer->remove($arguments['as']);

        return $output;
    }
}
