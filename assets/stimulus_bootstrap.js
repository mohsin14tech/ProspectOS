import { startStimulusApp } from '@symfony/stimulus-bundle';
import AiProviderController from './controllers/ai_provider_controller.js';
import MailboxController from './controllers/mailbox_controller.js';

const app = startStimulusApp();
app.register('ai-provider', AiProviderController);
app.register('mailbox', MailboxController);
