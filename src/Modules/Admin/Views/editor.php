<?php
$buttons = [
    'bold'                 => '<button type="button" data-cmd="bold"><strong>B</strong></button>',
    'italic'               => '<button type="button" data-cmd="italic"><em>I</em></button>',
    'underline'            => '<button type="button" data-cmd="underline"><u>U</u></button>',
    'insertUnorderedList'  => '<button type="button" data-cmd="insertUnorderedList">UL</button>',
    'insertOrderedList'    => '<button type="button" data-cmd="insertOrderedList">OL</button>',
    'createLink'           => '<button type="button" data-cmd="createLink">A</button>',
    'insertTable'          => '<button type="button" data-cmd="insertTable" title="Insert Table">Table</button>',
    'removeFormat'         => '<button type="button" data-cmd="removeFormat">Clear</button>',
];
$toolbarSets = [
    'full'  => array_keys($buttons),
    'basic' => ['bold', 'italic', 'underline', 'insertUnorderedList', 'insertOrderedList', 'createLink', 'removeFormat'],
];
$activeButtons = $toolbarSets[$toolbar ?? 'full'] ?? $toolbarSets['full'];
?>
<div class="editor">
  <div class="toolbar">
    <?php foreach ($activeButtons as $key): ?>
        <?php echo $buttons[$key]; ?>
    <?php endforeach; ?>
  </div>
  <div class="editor-area" contenteditable="true"><?php
use Zero\Support\Str; echo $record->{$field} ?? ''; ?></div>
  <input type="hidden" name="<?php echo Str::escape($field ?? 'content'); ?>" class="content-input" value="<?php echo Str::escape($record->{$field} ?? ''); ?>">
</div>
