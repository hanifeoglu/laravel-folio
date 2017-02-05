<?php
// hide credits?
if(!isset($hide_credits)) {
  $footer_credits = Config::get('space.footer');
  $hide_credits = false;
  if(isset($footer_credits['hide_credits'])) {
    $hide_credits = $footer_credits['hide_credits'];
  }
}
?>

<div class="[ u-pad-b-1x u-pad-t-1x ]">

  <div class="[ o-wrap o-wrap--size-tiny o-wrap--portable-size-minuscule u-pad-b-2x ]">
    {!! view('space::partial.c-footer__subscribe') !!}
  </div>

  @if(!$hide_credits)
  <div class="[ o-wrap o-wrap--size-medium ]">
    @if(isset($credits_text))
      {!! view('space::partial.c-footer__credits')->with(['text' => $credits_text]) !!}
    @else
      {!! view('space::partial.c-footer__credits') !!}
    @endif
  </div>
  @endif

</div>
