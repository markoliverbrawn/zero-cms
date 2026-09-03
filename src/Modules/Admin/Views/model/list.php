<?php
use Zero\Core\App;
use Zero\Core\Template;
use Zero\Support\AssetVersion;
use Zero\Support\Assets;
use Zero\Support\Str;
use Zero\Support\I18n;
?>
<div class="listrecords">
  <?php
  $site = App::getCurrentSite();
  $titleKey = strtolower($modelName ?? '');
  $translatedTitle = I18n::t($titleKey);
  $displayName = ($translatedTitle !== $titleKey) ? $translatedTitle : ucwords(str_replace('_', ' ', $modelName ?? ''));

  // If status is trash, dynamically swap 'created_at' with 'deleted_at' in the list configuration!
  if (($status ?? 'active') === 'trash') {
      $newConfig = [];
      foreach ($config as $field => $fieldConfig) {
          if ($field === 'created_at') {
              $newConfig['deleted_at'] = [
                  'type' => 'datetime',
                  'label' => 'Deleted At',
                  'editable' => false,
                  'listDisplay' => true
              ];
          } else {
              $newConfig[$field] = $fieldConfig;
          }
      }
      $config = $newConfig;
  }

  // Pre-load cascading relationships schemas for this model class once
  $modelClass = App::getModelClass($modelName ?? '');
  $editLabel = 'Edit';
  if ($modelClass && method_exists($modelClass, 'getEditLabel')) {
      $editLabel = $modelClass::getEditLabel();
  }
  $cascadeModels = [];
  if ($modelClass && class_exists($modelClass)) {
      if (method_exists($modelClass, 'getCascadeDeletes')) {
          try {
              $instance = new $modelClass();
              $cascadeModels = $instance->getCascadeDeletes();
          } catch (\Exception $e) {}
      } elseif (property_exists($modelClass, 'cascadeDeletes')) {
          try {
              $ref = new \ReflectionClass($modelClass);
              $prop = $ref->getProperty('cascadeDeletes');
              $prop->setAccessible(true);
              $cascadeModels = $prop->getValue();
          } catch (\Exception $e) {}
      }
  }
  ?>
  <h2><?php echo Str::escape($displayName); ?></h2>
  
  <div class="list-tabs-container">
      <a href="/admin/list/<?php echo Str::escape($modelName ?? ''); ?>?status=active" class="list-tab-link <?php echo ($status === 'active') ? 'active' : ''; ?>">View Active</a>
      <a href="/admin/list/<?php echo Str::escape($modelName ?? ''); ?>?status=trash" class="list-tab-link <?php echo ($status === 'trash') ? 'active' : ''; ?>">View Trash</a>
  </div>

  <div class="list-actions-bar">
    <form method="get" action="/admin/list/<?php echo Str::escape($modelName ?? ''); ?>" class="search-form">
      <?php echo App::makeFormField('text', 'q', [
          'value' => $q ?? '',
          'attributes' => ['class' => 'admin-search-input', 'placeholder' => 'Search...'],
          'showLabel' => false,
          'guessHelperTextKey' => false,
      ])->render(); ?>
      <input type="hidden" name="status" value="<?php echo Str::escape($status ?? 'active'); ?>" />
      <button type="submit">Search</button>
    </form>

    <div class="list-action-btn-wrapper">
      <?php if ($modelName !== 'audit_logs'): ?>
          <a href="/admin/edit/<?php echo Str::escape($modelName ?? ''); ?>/new" class="btn btn-save">New</a>
          <?php foreach (App::getModelListActions($modelName ?? '') as $listAction): ?>
            <?php if (App::isModelListActionVisible($listAction, $site)): ?>
              <?php if (($listAction['method'] ?? 'get') === 'post'): ?>
                <button type="button" class="btn btn-continue list-registered-action-btn"
                        data-url="<?php echo Str::escape($listAction['url']); ?>"
                        data-csrf="<?php echo Str::escape($csrf ?? ''); ?>"
                        data-label="<?php echo Str::escape($listAction['label']); ?>"
                        data-confirm="<?php echo Str::escape($listAction['confirm']); ?>"><?php echo Str::escape($listAction['label']); ?></button>
              <?php else: ?>
                <a href="<?php echo Str::escape($listAction['url']); ?>" class="btn btn-continue"><?php echo Str::escape($listAction['label']); ?></a>
              <?php endif; ?>
            <?php endif; ?>
          <?php endforeach; ?>
      <?php else: ?>
          <a href="/admin/export/<?php echo Str::escape($modelName ?? ''); ?>" class="btn btn-continue">Export CSV</a>
          <button type="button" id="btn-purge-logs" class="btn btn-danger" data-csrf="<?php echo Str::escape($csrf ?? ''); ?>" data-is-super="<?php echo App::authorize('audit.purge_global') ? '1' : '0'; ?>">Purge Logs</button>
      <?php endif; ?>
    </div>
  </div>

  <?php if (empty($records)): ?>
    <p class="text-muted"><?php echo I18n::t('no_records_found'); ?></p>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <?php if (($isOrderable ?? false) && $status === 'active'): ?>
            <th class="drag-handle-header"></th>
          <?php endif; ?>
          <?php foreach ($config as $field => $fieldConfig): ?>
            <?php if (($fieldConfig['listDisplay'] ?? true)): ?>
              <th>
                <a href="?sort=<?php echo Str::escape($field ?? ''); ?>&order=<?php echo ($sort === $field && $order === 'asc') ? 'desc' : 'asc'; ?>&q=<?php echo Str::escape($q ?? ''); ?>&status=<?php echo Str::escape($status ?? 'active'); ?>">
                  <?php echo Str::escape($fieldConfig['label'] ?? ''); ?>
                  <?php if ($sort === $field): ?>
                    <span style="font-size: 0.75rem; color: var(--accent-color, #2563eb);"><?php echo $order === 'asc' ? '▲' : '▼'; ?></span>
                  <?php endif; ?>
                </a>
              </th>
            <?php endif; ?>
          <?php endforeach; ?>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($records as $record): ?>
          <tr 
            <?php if (($isOrderable ?? false) && $status === 'active'): ?>
              draggable="false" 
              class="draggable-row"
            <?php endif; ?>
            data-id="<?php echo Str::escape($record->id ?? ''); ?>"
          >
            <?php if (($isOrderable ?? false) && $status === 'active'): ?>
              <td class="drag-handle-cell">⋮⋮</td>
            <?php endif; ?>
            
            <?php 
              $isFirstColumn = true; 
            ?>
            <?php foreach ($config as $field => $fieldConfig): ?>
              <?php if (($fieldConfig['listDisplay'] ?? true)): ?>
                <td data-field="<?php echo Str::escape($field ?? ''); ?>" data-label="<?php echo Str::escape($fieldConfig['label'] ?? ''); ?>">
                  <?php if ($isFirstColumn && $status !== 'trash'): ?>
                    <a href="/admin/edit/<?php echo Str::escape($modelName ?? ''); ?>/<?php echo Str::escape($record->id ?? ''); ?>" class="list-first-column-link">
                  <?php endif; ?>

                  <?php $columnRenderer = App::getModelColumnRenderer($modelName ?? '', $field); ?>
                  <?php if ($columnRenderer !== null): ?>
                    <?php echo $columnRenderer($record->{$field} ?? null, $record); ?>
                  <?php elseif (isset($fieldConfig['renderWith']) && \is_callable($fieldConfig['renderWith'])): ?>
                    <?php echo ($fieldConfig['renderWith'])($record->{$field} ?? null, $record); ?>
                  <?php elseif (!empty($fieldConfig['listView'])): ?>
                    <?php
                      $viewPath = App::resolveListView($fieldConfig['listView']);
                      if ($viewPath !== null) {
                          echo Template::renderFile($viewPath, ['value' => $record->{$field}, 'record' => $record]);
                      } else {
                          echo Str::escape($record->{$field} ?? '');
                      }
                    ?>
                  <?php elseif (($field === 'main_image' || $field === 'featured_image') && !empty($record->{$field})): ?>
                    <div class="list-thumbnail-box">
                      <img src="<?php echo Str::escape(Assets::url($record->{$field}, width: 120)); ?>" class="list-thumbnail-img" alt="Thumbnail" />
                    </div>
                  <?php elseif ($field === 'comment_count'): ?>
                    <?php if ($record->comment_count > 0): ?>
                      <a href="/admin/list/comments?q=<?php echo urlencode($record->title); ?>" class="comment-count-link">
                        <span class="comment-count-badge" title="Click to view comments">
                          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="admin-comment-icon"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                          <?php echo $record->comment_count; ?>
                        </span>
                      </a>
                    <?php else: ?>
                      <span class="text-muted" style="font-size: 0.85rem; font-style: italic;">0 comments</span>
                    <?php endif; ?>
                  <?php else: ?>
                    <?php 
                      if (($fieldConfig['type'] ?? '') === 'datetime' && !empty($record->{$field})) {
                          echo Str::escape(I18n::localizeDateTime($record->{$field}));
                      } else {
                          $val = $record->{$field} ?? '';
                          if (($fieldConfig['type'] ?? '') === 'rich_text') {
                              $val = strip_tags($val);
                          }
                          echo Str::escape($val);
                      }
                    ?>
                  <?php endif; ?>

                  <?php if ($isFirstColumn && $status !== 'trash'): ?>
                    </a>
                  <?php endif; ?>
                </td>
                <?php $isFirstColumn = false; ?>
              <?php endif; ?>
            <?php endforeach; ?>
            <td class="row-actions-cell">
              <?php
              $isHomepageRow = $record && method_exists($record, 'isHomepage') && $record->isHomepage();
              // Registered row actions only surface on active rows -- a trashed/soft-deleted record
              // isn't a sensible target for a module's own custom action (e.g. "send test email").
              $rowActions = ($status !== 'trash') ? App::getModelRowActions($modelName ?? '') : [];
              ?>
              <div class="row-actions-menu">
                <button type="button" class="row-actions-trigger" aria-haspopup="true" aria-expanded="false" aria-label="Row actions">
                  <?php echo App::svg('more-vertical'); ?>
                </button>
                <div class="row-actions-dropdown" role="menu">
                  <?php if ($status === 'trash'): ?>
                    <form method="post" action="/admin/restore/<?php echo Str::escape($modelName ?? ''); ?>" class="admin-restore-form">
                      <input type="hidden" name="csrf" value="<?php echo Str::escape($csrf ?? ''); ?>">
                      <input type="hidden" name="id" value="<?php echo Str::escape($record->id ?? ''); ?>">
                      <button type="submit" role="menuitem" class="btn-restore-link">Restore</button>
                    </form>
                    <?php if ($isHomepageRow): ?>
                      <button type="button" role="menuitem" class="btn-force-delete-link" disabled title="The designated site homepage cannot be deleted.">Delete Permanently</button>
                    <?php else: ?>
                      <form method="post" action="/admin/force-delete/<?php echo Str::escape($modelName ?? ''); ?>" class="admin-force-delete-form" data-id="<?php echo Str::escape($record->id ?? ''); ?>" data-model="<?php echo Str::escape($modelName ?? ''); ?>">
                        <input type="hidden" name="csrf" value="<?php echo Str::escape($csrf ?? ''); ?>">
                        <input type="hidden" name="id" value="<?php echo Str::escape($record->id ?? ''); ?>">
                        <button type="submit" role="menuitem" class="btn-force-delete-link">Delete Permanently</button>
                      </form>
                    <?php endif; ?>
                  <?php else: ?>
                    <a href="/admin/edit/<?php echo Str::escape($modelName ?? ''); ?>/<?php echo Str::escape($record->id ?? ''); ?>" role="menuitem"><span class="row-action-icon"><?php echo App::svg('edit-3'); ?></span><?php echo Str::escape($editLabel); ?></a>
                    <?php foreach ($rowActions as $rowAction): ?>
                      <?php if (App::isModelRowActionVisible($rowAction, $site)): ?>
                        <?php $rowActionUrl = \str_replace('{id}', \rawurlencode((string)($record->id ?? '')), $rowAction['url']); ?>
                        <?php $rowActionIcon = !empty($rowAction['icon']) ? '<span class="row-action-icon">' . App::svg($rowAction['icon']) . '</span>' : ''; ?>
                        <?php if (($rowAction['method'] ?? 'get') === 'post'): ?>
                          <button type="button" role="menuitem" class="list-registered-row-action-btn"
                                  data-url="<?php echo Str::escape($rowActionUrl); ?>"
                                  data-csrf="<?php echo Str::escape($csrf ?? ''); ?>"
                                  data-label="<?php echo Str::escape($rowAction['label']); ?>"
                                  data-confirm="<?php echo Str::escape($rowAction['confirm']); ?>"><?php echo $rowActionIcon; ?><?php echo Str::escape($rowAction['label']); ?></button>
                        <?php else: ?>
                          <a href="<?php echo Str::escape($rowActionUrl); ?>" role="menuitem"><?php echo $rowActionIcon; ?><?php echo Str::escape($rowAction['label']); ?></a>
                        <?php endif; ?>
                      <?php endif; ?>
                    <?php endforeach; ?>
                    <div class="row-actions-divider"></div>
                    <form method="post" action="/admin/delete/<?php echo Str::escape($modelName ?? ''); ?>" class="admin-delete-form" data-id="<?php echo Str::escape($record->id ?? ''); ?>" data-model="<?php echo Str::escape($modelName ?? ''); ?>">
                      <input type="hidden" name="csrf" value="<?php echo Str::escape($csrf ?? ''); ?>">
                      <input type="hidden" name="id" value="<?php echo Str::escape($record->id ?? ''); ?>">
                      <?php if ($isHomepageRow): ?>
                        <button type="button" role="menuitem" class="btn-delete-link" disabled style="opacity: 0.4; cursor: not-allowed;" title="The designated site homepage cannot be deleted."><span class="row-action-icon"><?php echo App::svg('trash-2'); ?></span>Delete</button>
                      <?php else: ?>
                        <button type="submit" role="menuitem" class="btn-delete-link"><span class="row-action-icon"><?php echo App::svg('trash-2'); ?></span>Delete</button>
                      <?php endif; ?>
                    </form>
                  <?php endif; ?>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php echo App::renderPagination(['currentPage' => $page ?? 1, 'totalPages' => $pages ?? 1], '/admin/list/' . ($modelName ?? ''), $_GET); ?>
  <?php endif; ?>
</div>

<?php if (($isOrderable ?? false) && $status === 'active'): ?>
<script nonce="<?php echo App::getNonce(); ?>">
window.ADMIN_MODEL_NAME = "<?php echo Str::escape($modelName ?? ''); ?>";
</script>
<?php endif; ?>
<script src="<?php echo Str::escape(AssetVersion::url('/assets/js/admin/model_list.js')); ?>"></script>
