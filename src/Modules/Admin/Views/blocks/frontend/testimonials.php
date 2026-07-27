<?php
use Zero\Support\Str; 
$duration = $block['duration'] ?? 5000;
$items = $block['items'] ?? [];
?>
<div class="block block-testimonials" data-duration="<?php echo $duration; ?>">
  
  <div class="testimonials-carousel-container">
    <div class="testimonials-slides-wrapper">
      <?php foreach ($items as $item): ?>
        <div class="testimonial-slide">
          <div class="quote">"<?php echo $item['content'] ?? ''; ?>"</div>
          <p class="author">— <?php echo Str::escape($item['person'] ?? ''); ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
