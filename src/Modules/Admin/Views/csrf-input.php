<input type="hidden" name="csrf" value="<?php
use Zero\Support\Str; echo Str::escape($csrf ?? ''); ?>">