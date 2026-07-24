<?php use Zero\Support\I18n; ?>
<div class="listrecords">
  <?php
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
  $modelClass = \Zero\Core\App::getModelClass($modelName ?? '');
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
  <h2><?php echo htmlspecialchars($displayName, ENT_QUOTES, "UTF-8"); ?></h2>
  
  <div class="list-tabs-container">
      <a href="/admin/list/<?php echo htmlspecialchars($modelName ?? ''); ?>?status=active" class="list-tab-link <?php echo ($status === 'active') ? 'active' : ''; ?>">View Active</a>
      <a href="/admin/list/<?php echo htmlspecialchars($modelName ?? ''); ?>?status=trash" class="list-tab-link <?php echo ($status === 'trash') ? 'active' : ''; ?>">View Trash</a>
  </div>

  <div class="list-actions-bar">
    <form method="get" action="/admin/list/<?php echo htmlspecialchars($modelName ?? '', ENT_QUOTES, "UTF-8"); ?>" class="search-form">
      <input type="text" name="q" placeholder="Search..." value="<?php echo htmlspecialchars($q ?? '', ENT_QUOTES, "UTF-8"); ?>" class="admin-search-input" />
      <input type="hidden" name="status" value="<?php echo htmlspecialchars($status ?? 'active', ENT_QUOTES, "UTF-8"); ?>" />
      <button type="submit">Search</button>
    </form>

    <div class="list-action-btn-wrapper">
      <?php if ($modelName !== 'audit_logs'): ?>
          <a href="/admin/edit/<?php echo htmlspecialchars($modelName ?? '', ENT_QUOTES, "UTF-8"); ?>/new" class="btn btn-save">New</a>
      <?php else: ?>
          <a href="/admin/export/<?php echo htmlspecialchars($modelName ?? '', ENT_QUOTES, "UTF-8"); ?>" class="btn btn-continue">Export CSV</a>
          <button type="button" id="btn-purge-logs" class="btn btn-danger" data-csrf="<?php echo htmlspecialchars($csrf ?? '', ENT_QUOTES, "UTF-8"); ?>" data-is-super="<?php echo (\Zero\Core\App::getCurrentUserRole() === 'super_admin') ? '1' : '0'; ?>">Purge Logs</button>
      <?php endif; ?>
    </div>
  </div>

  <?php if (empty($records)): ?>
    <p class="text-muted"><?php echo \Zero\Support\I18n::t('no_records_found'); ?></p>
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
                <a href="?sort=<?php echo htmlspecialchars($field ?? '', ENT_QUOTES, "UTF-8"); ?>&order=<?php echo ($sort === $field && $order === 'asc') ? 'desc' : 'asc'; ?>&q=<?php echo htmlspecialchars($q ?? '', ENT_QUOTES, "UTF-8"); ?>&status=<?php echo htmlspecialchars($status ?? 'active', ENT_QUOTES, "UTF-8"); ?>">
                  <?php echo htmlspecialchars($fieldConfig['label'] ?? '', ENT_QUOTES, "UTF-8"); ?>
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
            data-id="<?php echo htmlspecialchars($record->id ?? '', ENT_QUOTES, "UTF-8"); ?>"
          >
            <?php if (($isOrderable ?? false) && $status === 'active'): ?>
              <td class="drag-handle-cell">⋮⋮</td>
            <?php endif; ?>
            
            <?php 
              $isFirstColumn = true; 
            ?>
            <?php foreach ($config as $field => $fieldConfig): ?>
              <?php if (($fieldConfig['listDisplay'] ?? true)): ?>
                <td data-field="<?php echo htmlspecialchars($field ?? '', ENT_QUOTES, "UTF-8"); ?>" data-label="<?php echo htmlspecialchars($fieldConfig['label'] ?? '', ENT_QUOTES, "UTF-8"); ?>">
                  <?php if ($isFirstColumn && $status !== 'trash'): ?>
                    <a href="/admin/edit/<?php echo htmlspecialchars($modelName ?? '', ENT_QUOTES, "UTF-8"); ?>/<?php echo htmlspecialchars($record->id ?? '', ENT_QUOTES, "UTF-8"); ?>" class="list-first-column-link">
                  <?php endif; ?>

                  <?php if (!empty($fieldConfig['listView'])): ?>
                    <?php 
                      $viewPath = APPLICATION_ROOT . '/src/Modules/Admin/Views/' . $fieldConfig['listView'] . '.php';
                      if (file_exists($viewPath)) {
                          echo \Zero\Core\Template::renderFile($viewPath, ['value' => $record->{$field}, 'record' => $record]);
                      } else {
                          echo htmlspecialchars($record->{$field} ?? '', ENT_QUOTES, "UTF-8");
                      }
                    ?>
                  <?php elseif (($field === 'main_image' || $field === 'featured_image') && !empty($record->{$field})): ?>
                    <div class="list-thumbnail-box">
                      <img src="<?php echo htmlspecialchars($record->{$field}); ?>" class="list-thumbnail-img" alt="Thumbnail" />
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
                          echo htmlspecialchars(I18n::localizeDateTime($record->{$field}), ENT_QUOTES, "UTF-8");
                      } else {
                          echo htmlspecialchars($record->{$field} ?? '', ENT_QUOTES, "UTF-8");
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
            <td>
              <?php
              $isHomepageRow = $record && method_exists($record, 'isHomepage') && $record->isHomepage();
              ?>
              <?php if ($status === 'trash'): ?>
                <form method="post" action="/admin/restore/<?php echo htmlspecialchars($modelName ?? '', ENT_QUOTES, "UTF-8"); ?>" class="admin-restore-form">
                  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf ?? '', ENT_QUOTES, "UTF-8"); ?>">
                  <input type="hidden" name="id" value="<?php echo htmlspecialchars($record->id ?? '', ENT_QUOTES, "UTF-8"); ?>">
                  <button type="submit" class="btn-restore-link">Restore</button>
                </form>
                <?php if ($isHomepageRow): ?>
                  <button type="button" class="btn-force-delete-link" disabled title="The designated site homepage cannot be deleted.">Delete Permanently</button>
                <?php else: ?>
                  <form method="post" action="/admin/force-delete/<?php echo htmlspecialchars($modelName ?? '', ENT_QUOTES, "UTF-8"); ?>" class="admin-force-delete-form" data-id="<?php echo htmlspecialchars($record->id ?? '', ENT_QUOTES, "UTF-8"); ?>" data-model="<?php echo htmlspecialchars($modelName ?? '', ENT_QUOTES, "UTF-8"); ?>">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf ?? '', ENT_QUOTES, "UTF-8"); ?>">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($record->id ?? '', ENT_QUOTES, "UTF-8"); ?>">
                    <button type="submit" class="btn-force-delete-link">Delete Permanently</button>
                  </form>
                <?php endif; ?>
              <?php else: ?>
                <a href="/admin/edit/<?php echo htmlspecialchars($modelName ?? '', ENT_QUOTES, "UTF-8"); ?>/<?php echo htmlspecialchars($record->id ?? '', ENT_QUOTES, "UTF-8"); ?>"><?php echo htmlspecialchars($editLabel, ENT_QUOTES, "UTF-8"); ?></a>
                <form method="post" action="/admin/delete/<?php echo htmlspecialchars($modelName ?? '', ENT_QUOTES, "UTF-8"); ?>" class="admin-delete-form" data-id="<?php echo htmlspecialchars($record->id ?? '', ENT_QUOTES, "UTF-8"); ?>" data-model="<?php echo htmlspecialchars($modelName ?? '', ENT_QUOTES, "UTF-8"); ?>">
                  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf ?? '', ENT_QUOTES, "UTF-8"); ?>">
                  <input type="hidden" name="id" value="<?php echo htmlspecialchars($record->id ?? '', ENT_QUOTES, "UTF-8"); ?>">
                  <?php if ($isHomepageRow): ?>
                    <button type="button" class="btn-delete-link" disabled style="opacity: 0.4; cursor: not-allowed;" title="The designated site homepage cannot be deleted.">Delete</button>
                  <?php else: ?>
                    <button type="submit" class="btn-delete-link">Delete</button>
                  <?php endif; ?>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php include APPLICATION_ROOT . '/src/Modules/Admin/Views/pagination.php'; ?>
  <?php endif; ?>
</div>

<?php if (($isOrderable ?? false) && $status === 'active'): ?>
<script nonce="<?php echo \Zero\Core\App::getNonce(); ?>">
window.ADMIN_MODEL_NAME = "<?php echo htmlspecialchars($modelName ?? '', ENT_QUOTES, 'UTF-8'); ?>";
</script>
<?php endif; ?>
<script src="/assets/js/admin/model_list.js"></script>
