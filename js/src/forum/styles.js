let loading = null;

/**
 * Garage's page styles: a chunk of their own, fetched once beside the first
 * Garage page or window that opens (the routes load both at once, so nothing
 * shows unstyled), and never part of forum.css.
 */
export function loadStyles() {
  return (loading ??= import('./generated/pagesCss').then(({ default: css }) => {
    if (document.getElementById('garage-styles')) return;
    const style = document.createElement('style');
    style.id = 'garage-styles';
    style.textContent = css;
    document.head.appendChild(style);
  }));
}

/** For components that can open without their route (a window from another page). */
export default function useStyles() {
  loadStyles();
}
