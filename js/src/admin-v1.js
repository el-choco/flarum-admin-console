import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import createConsolePage from './ConsolePage';

const ConsolePage = createConsolePage(app, ExtensionPage, m);
// Export a real module object, including an extender list, for bootExtensions.
export const extend = [];
app.initializers.add('el-choco-admin-console', () => {
  app.extensionData.for('el-choco-admin-console').registerPage(ConsolePage);
});
