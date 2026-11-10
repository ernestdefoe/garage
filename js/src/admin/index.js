import app from 'flarum/admin/app';
import { registerModels } from '../common/models';

export { default as extend } from './extend';

app.initializers.add('ernestdefoe-garage', () => {
  registerModels(app);
});
