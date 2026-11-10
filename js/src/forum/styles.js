import css from '../../generated/pagesCss';

/** Garage's page styles, added once, the first time one of its pages or windows is shown. */
export default function useStyles() {
  if (document.getElementById('garage-styles')) return;
  const style = document.createElement('style');
  style.id = 'garage-styles';
  style.textContent = css;
  document.head.appendChild(style);
}
