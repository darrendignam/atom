<?php

/*
 * This file is part of the Access to Memory (AtoM) software.
 *
 * Access to Memory (AtoM) is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Access to Memory (AtoM) is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Access to Memory (AtoM).  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Import authority record relations using CSV
 *
 * @package    AccessToMemory
 * @subpackage task
 * @author     Mike Cantelon <mike@artefactual.com>
 */
class csvAuthorityRecordRelationImportTask extends arBaseTask
{
  /**
   * @see sfTask
   */
  protected function configure()
  {
    $this->addArguments([
      new sfCommandArgument('filename', sfCommandArgument::REQUIRED, 'Output filename')
    ]);

    $this->addOptions([
      new sfCommandOption('application', null,
        sfCommandOption::PARAMETER_OPTIONAL, 'The application name', true),
      new sfCommandOption('env', null,
        sfCommandOption::PARAMETER_REQUIRED, 'The environment', 'cli'),
      new sfCommandOption('connection', null,
      sfCommandOption::PARAMETER_REQUIRED, 'The connection name', 'propel'),

      new sfCommandOption('index', null,
        sfCommandOption::PARAMETER_NONE, 'Index for search during import.'),
    ]);

    $this->namespace = 'csv';
    $this->name = 'authority-relation-import';
    $this->briefDescription = 'Import authority record relations using CSV data.';
    $this->detailedDescription = <<<EOF
      Import authority record relations using CSV data
EOF;
  }

  /**
   * @see sfTask
   */
  public function execute($arguments = [], $options = [])
  {
    parent::execute($arguments, $options);

    $this->log('Importing relations...');
    $this->import($arguments['filename'], $options['index']);
    $this->log('Done.');
  }

  private function import($filepath, $indexDuringImport = false)
  {
    if (false === $fh = fopen($filepath, 'rb'))
    {
      throw new sfException('You must specify a valid filename');
    }

    // Load taxonomies into variables to avoid use of magic numbers
    $termData = QubitFlatfileImport::loadTermsFromTaxonomies([
      QubitTaxonomy::ACTOR_RELATION_TYPE_ID => 'actorRelationTypes',
    ]);

    $import = new QubitFlatfileImport([
      'context' => sfContext::createInstance($this->configuration),

      'status' => [
        'actorRelationTypes' => $termData['actorRelationTypes'],
        'actorIds'           => [],
      ],

      'variableColumns' => [
        'sourceAuthorizedFormOfName',
        'targetAuthorizedFormOfName',
        'category',
        'description',
        'date',
        'startDate',
        'endDate'
      ],

      'saveLogic' => function($self)
      {
        // Figure out ID of the two actors
        $sourceActor = QubitActor::getByAuthorizedFormOfName(
          $self->rowStatusVars['sourceAuthorizedFormOfName'], ['culture' => $self->columnValue('culture')]);
        $targetActor = QubitActor::getByAuthorizedFormOfName(
          $self->rowStatusVars['targetAuthorizedFormOfName'], ['culture' => $self->columnValue('culture')]);

        // Determine type ID of relationship type
        $relationTypeId = array_search(
          $self->rowStatusVars['category'],
          $self->status['actorRelationTypes'][$self->columnValue('culture')]
        );

        if (!$relationTypeId)
        {
          throw new sfException(sprintf('Unknown relationship type %s:', $self->rowStatusVars['category']));
        }
        else
        {
          // Attempt to add relationship
          if (empty($sourceActor) || empty($targetActor))
          {
            // Warn if actor is missing
            $badActor = (empty($sourceActor))
              ? $self->rowStatusVars['sourceAuthorizedFormOfName']
              : $self->rowStatusVars['targetAuthorizedFormOfName'];

            $error = sprintf('Actor "%s" does not exist', $badActor);
            print $self->logError($error);
          }
          else
          {
            // Add relationship if it doesn't yet exist
            if (!$this->checkIfRelationshipExists($sourceActor->id, $targetActor->id, $relationTypeId))
            {
              $relation = new QubitRelation;
              $relation->subjectId = $sourceActor->id;
              $relation->objectId  = $targetActor->id;
              $relation->typeId    = $relationTypeId;

              // Set relationship properties from column values
              foreach (['date', 'startDate', 'endDate', 'description'] as $property)
              {
                if (!empty($self->rowStatusVars[$property]))
                {
                  $relation->$property = $self->rowStatusVars[$property];
                }
              }

              $relation->save();
            }
          }
        }
      }
    ]);

    // Allow search indexing to be enabled via a CLI option
    $import->searchIndexingDisabled = !$indexDuringImport;

    $import->csv($fh);
  }

  private function checkIfRelationshipExists($sourceActorId, $targetActorId, $relationTypeId)
  {
    $sql = "SELECT id FROM relation \r
      WHERE subject_id = :subject_id \r
      AND object_id = :object_id \r
      AND type_id = :type_id";

    $params = [
      ':subject_id' => $sourceActorId,
      ':object_id' => $targetActorId,
      ':type_id' => $relationTypeId
    ];

    $paramsVariant = [
      ':subject_id' => $targetActorId,
      ':object_id' => $sourceActorId,
      ':type_id' => $relationTypeId
    ];

    return QubitPdo::fetchOne($sql, $params) !== false
      || QubitPdo::fetchOne($sql, $paramsVariant) !== false;
  }
}
