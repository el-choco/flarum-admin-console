import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Admin from 'flarum/common/extenders/Admin';
import createConsolePage from './ConsolePage';

const ConsolePage = createConsolePage(app, ExtensionPage, m);
// Flarum 2 registers pages through the Admin extender / registry.
export const extend = [new Admin().page(ConsolePage)];
