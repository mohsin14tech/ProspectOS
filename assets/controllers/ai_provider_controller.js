import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'provider', 'model', 'modelLabel', 'modelHelp', 'apiKeyField', 'apiKey', 'keyHelp',
        'keyLink', 'localEndpointField', 'localEndpoint', 'routeName', 'routeKind', 'routeSummary', 'routeDisclosure', 'ollamaGuide',
        'saveButton',
    ];

    connect() {
        this.initialProvider = this.providerTarget.value;
        this.updateProvider(false);
    }

    providerChanged() {
        if (this.providerTarget.value !== this.initialProvider) {
            const option = this.providerTarget.selectedOptions[0];
            this.modelTarget.value = option?.dataset.savedModel || '';
            if (this.hasLocalEndpointTarget) {
                this.localEndpointTarget.value = option?.dataset.savedEndpoint || 'http://host.docker.internal:11434/v1';
            }
        }
        this.updateProvider(true);
    }

    updateProvider(afterChange) {
        const option = this.providerTarget.selectedOptions[0];
        if (!option) return;

        const provider = option.value;
        const kind = option.dataset.kind;
        const requiresKey = option.dataset.requiresKey === 'true';
        const hasSavedKey = option.dataset.savedKey === 'true';
        const isOllama = provider === 'ollama';

        this.apiKeyFieldTarget.hidden = !requiresKey;
        this.apiKeyTarget.required = requiresKey && !hasSavedKey;
        this.localEndpointFieldTarget.hidden = !isOllama;
        this.ollamaGuideTarget.hidden = !isOllama;
        this.modelLabelTarget.textContent = isOllama ? 'Installed model name' : 'Model ID';
        this.modelHelpTarget.textContent = isOllama
            ? 'Use the exact name shown by `ollama list`. ProspectOS will not download the model for you.'
            : 'Use the model ID shown in the selected provider account. Names and availability can change.';

        this.routeNameTarget.textContent = option.dataset.name;
        this.routeKindTarget.textContent = kind === 'local' ? 'Local model' : (kind === 'cloud' ? 'Cloud provider' : 'AI off');
        this.routeSummaryTarget.textContent = option.dataset.summary;
        this.routeDisclosureTarget.textContent = kind === 'local'
            ? 'Research context is sent to the local Ollama endpoint shown below.'
            : (kind === 'cloud' ? 'Research context leaves ProspectOS and is sent to this provider.' : 'No model request will be made.');
        this.saveButtonTarget.textContent = kind === 'off' ? 'Turn AI off for this workspace' : `Save ${option.dataset.name} settings`;

        if (requiresKey) {
            if (hasSavedKey) {
                this.keyHelpTarget.textContent = 'A key is saved for this provider. Leave this blank to keep it, or enter a replacement. The saved value is never shown.';
            } else {
                this.keyHelpTarget.textContent = 'Your key is encrypted before it is stored. It is sent only when you run the provider test or generate an AI summary.';
            }
            this.keyLinkTarget.href = option.dataset.keyUrl;
            this.keyLinkTarget.textContent = `Get a ${option.dataset.name} key`;
            this.keyLinkTarget.hidden = false;
        } else {
            this.keyHelpTarget.textContent = isOllama ? 'Ollama needs no API key. Run the model server on your computer first.' : 'AI is disabled for this workspace.';
            this.keyLinkTarget.hidden = true;
        }

        if (afterChange && this.providerTarget.value !== this.initialProvider) {
            this.apiKeyTarget.value = '';
        }
    }
}
