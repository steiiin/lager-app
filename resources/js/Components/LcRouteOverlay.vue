<!--
 * LcRouteOverlay - Component
 *
 * Only a simple semi-transparent overlay which is visible while navigating to the next route.
 *
 */-->
<template>
  <div class="lc-routeoverlay">
    &nbsp;
  </div>
</template>
<style lang="scss" scoped>
.lc-routeoverlay {
  background: var(--overlay-translucent-background);
  width: 100%;
  height: 100%;
  position: fixed;
  top: 0;
  left: 0;
}
</style>
