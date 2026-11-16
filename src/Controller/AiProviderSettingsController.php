<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AiProviderSetting;
use App\Entity\User;
use App\Entity\WorkspaceMembership;
use App\Service\AiProviderCatalog;
use App\Service\AiProviderConfiguration;
use App\Service\CurrentWorkspaceProvider;
use App\Service\SecretVault;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class AiProviderSettingsController extends AbstractController
{
    #[Route('/integrations/ai', name: 'app_integrations_ai', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        CurrentWorkspaceProvider $workspaceProvider,
        EntityManagerInterface $entityManager,
        AiProviderCatalog $catalog,
        AiProviderConfiguration $configuration,
        SecretVault $vault,
        RateLimiterFactory $aiSummaryLimiter,
    ): Response {
        $workspace = $workspaceProvider->get();
        $actor = $this->getUser();
        if (!$actor instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $membership = $entityManager->getRepository(WorkspaceMembership::class)->findOneBy(['workspace' => $workspace, 'user' => $actor]);
        $canManage = $membership instanceof WorkspaceMembership && in_array($membership->getRole(), [WorkspaceMembership::ROLE_OWNER, WorkspaceMembership::ROLE_ADMIN], true);
        if (!$canManage) {
            throw $this->createAccessDeniedException('Only workspace owners and admins can change AI provider settings.');
        }

        $repository = $entityManager->getRepository(AiProviderSetting::class);
        $setting = $repository->findOneBy(['workspace' => $workspace, 'active' => true]);
        if (!$setting instanceof AiProviderSetting) {
            $setting = null;
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('ai_provider_settings', (string) $request->request->get('_token', ''))) {
                $this->addFlash('error', 'Your session expired. Refresh the provider setup page and try again.');

                return $this->redirectToRoute('app_integrations_ai');
            }

            $action = (string) $request->request->get('action', 'save');
            if ('activate' === $action) {
                $provider = trim((string) $request->request->get('provider', ''));
                $connection = $repository->findOneBy(['workspace' => $workspace, 'provider' => $provider]);
                if (!$connection instanceof AiProviderSetting) {
                    $this->addFlash('error', 'Save this provider connection before selecting it as the active route.');
                } else {
                    foreach ($repository->findBy(['workspace' => $workspace]) as $savedConnection) {
                        $savedConnection->setActive($savedConnection === $connection);
                    }
                    $entityManager->flush();
                    $this->addFlash('success', 'Active AI route updated. The other provider connections remain saved.');
                }

                return $this->redirectToRoute('app_integrations_ai');
            }
            if ('test' === $action) {
                if (!$aiSummaryLimiter->create($actor->getUserIdentifier())->consume()->isAccepted()) {
                    $this->addFlash('error', 'This account reached the limit of 10 AI actions per hour. Try again later.');
                } else {
                    try {
                        $targetProvider = trim((string) $request->request->get('provider', $setting?->getProvider() ?? ''));
                        $latency = $configuration->test($workspace, $actor, $targetProvider !== '' ? $targetProvider : null);
                        $this->addFlash('success', sprintf('Provider test passed in %d ms. AI research is ready for this workspace.', $latency));
                    } catch (\Throwable) {
                        $this->addFlash('error', 'The provider test failed. Check the model name, saved key, provider account and network, then test again.');
                    }
                }

                return $this->redirectToRoute('app_integrations_ai');
            }

            try {
                $provider = trim((string) $request->request->get('provider', 'disabled'));
                $definition = $catalog->get($provider);
                $model = trim((string) $request->request->get('model', ''));
                if ('disabled' === $provider && '' === $model) {
                    $model = 'disabled';
                }
                if ('' === $model || mb_strlen($model) > 160) {
                    throw new \InvalidArgumentException('Enter the model identifier shown in your provider account (up to 160 characters).');
                }

                $localEndpoint = null;
                if ('ollama' === $provider) {
                    $localEndpoint = trim((string) $request->request->get('local_endpoint', 'http://host.docker.internal:11434/v1'));
                    if (!$catalog->isSafeLocalEndpoint($localEndpoint)) {
                        throw new \InvalidArgumentException('Use a local Ollama endpoint on host.docker.internal, localhost, 127.0.0.1 or ollama, with the local /v1 API path.');
                    }
                }

                $submittedKey = trim((string) $request->request->get('api_key', ''));
                if (mb_strlen($submittedKey) > 4096) {
                    throw new \InvalidArgumentException('The API key is too long. Check that you pasted only the key value.');
                }
                $encryptedKey = null;
                if ($definition['requiresKey']) {
                    if ('' !== $submittedKey) {
                        $encryptedKey = $vault->encrypt($submittedKey, 'ai-provider:'.$provider);
                    } elseif (($savedForProvider = $repository->findOneBy(['workspace' => $workspace, 'provider' => $provider])) instanceof AiProviderSetting && $savedForProvider->hasApiKey()) {
                        try {
                            $vault->decrypt((string) $savedForProvider->getEncryptedApiKey(), 'ai-provider:'.$provider);
                        } catch (\RuntimeException) {
                            throw new \InvalidArgumentException('The saved key cannot be opened with the current APP_SECRET. Enter the provider key again.');
                        }
                        $encryptedKey = $savedForProvider->getEncryptedApiKey();
                    } else {
                        throw new \InvalidArgumentException('Enter an API key for the selected provider. The key is encrypted before it is stored and cannot be viewed later.');
                    }
                }

                $savedForProvider = $repository->findOneBy(['workspace' => $workspace, 'provider' => $provider]);
                $makeActive = $provider === 'disabled' || !($setting instanceof AiProviderSetting) || $savedForProvider === $setting;
                if ($savedForProvider instanceof AiProviderSetting) {
                    $savedForProvider->replace($provider, $model, $localEndpoint, $encryptedKey, $actor, $makeActive);
                } else {
                    $savedForProvider = new AiProviderSetting($workspace, $actor, $provider, $model, $localEndpoint, $encryptedKey);
                    $savedForProvider->setActive($makeActive);
                    $entityManager->persist($savedForProvider);
                }
                if ($makeActive) {
                    foreach ($repository->findBy(['workspace' => $workspace]) as $otherConnection) {
                        if ($otherConnection !== $savedForProvider) {
                            $otherConnection->setActive(false);
                        }
                    }
                }
                $entityManager->flush();
                $this->addFlash('success', 'AI provider settings saved. Run the test below before generating summaries.');
            } catch (\InvalidArgumentException $exception) {
                $message = $exception->getMessage();
                $requestedProvider = trim((string) $request->request->get('provider', 'disabled'));
                $field = match (true) {
                    str_contains($message, 'model identifier') => 'model',
                    str_contains($message, 'Ollama endpoint') => 'local_endpoint',
                    str_contains($message, 'API key'), str_contains($message, 'saved key') => 'api_key',
                    default => null,
                };

                return $this->renderPage(
                    $workspace,
                    $setting,
                    $configuration,
                    $catalog,
                    in_array($requestedProvider, AiProviderSetting::PROVIDERS, true) ? $requestedProvider : null,
                    trim((string) $request->request->get('model', '')),
                    trim((string) $request->request->get('local_endpoint', 'http://host.docker.internal:11434/v1')),
                    $message,
                    $field,
                    $configuration->connections($workspace),
                );
            }

            return $this->redirectToRoute('app_integrations_ai');
        }

        return $this->renderPage($workspace, $setting, $configuration, $catalog, connections: $configuration->connections($workspace));
    }

    private function renderPage(
        \App\Entity\Workspace $workspace,
        ?AiProviderSetting $setting,
        AiProviderConfiguration $configuration,
        AiProviderCatalog $catalog,
        ?string $provider = null,
        ?string $model = null,
        ?string $localEndpoint = null,
        ?string $formError = null,
        ?string $formErrorField = null,
        array $connections = [],
    ): Response {
        $effectiveProvider = $setting?->getProvider() ?? $configuration->selectedProvider($workspace);
        if (!in_array($effectiveProvider, AiProviderSetting::PROVIDERS, true)) {
            $effectiveProvider = 'disabled';
        }
        $provider ??= $effectiveProvider;
        $definition = $catalog->get($provider);
        $editingConnection = $configuration->connection($workspace, $provider);
        $connectionRows = array_map(fn (AiProviderSetting $connection): array => [
            'setting' => $connection,
            'configured' => $configuration->isConnectionConfigured($connection),
            'name' => $catalog->get($connection->getProvider())['name'],
        ], $connections);
        $savedProviders = [];
        $savedConnectionData = [];
        foreach ($connections as $connection) {
            $savedProviders[$connection->getProvider()] = true;
            $savedConnectionData[$connection->getProvider()] = [
                'model' => $connection->getModel(),
                'localEndpoint' => $connection->getLocalEndpoint() ?? '',
            ];
        }
        $effectiveConfiguration = $configuration->isConfigured($workspace);
        $matchesSaved = $provider === $effectiveProvider;
        $ready = $matchesSaved && $configuration->isReady($workspace);
        $disclosure = match ($definition['kind']) {
            'local' => 'ProspectOS sends the prospect name, objective, evidence excerpts and relevant team decisions to your local Ollama server. This route uses the endpoint shown below and does not call a cloud AI provider.',
            'cloud' => sprintf('ProspectOS sends the prospect name, research objective, source URLs, bounded evidence and up to five similar team decisions to %s. Check the selected account’s data and billing terms before testing.', $definition['name']),
            default => 'AI analysis is off. No model request is sent.',
        };

        return $this->render('integrations/ai.html.twig', [
            'workspace' => $workspace,
            'providers' => $catalog->all(),
            'provider' => $provider,
            'effectiveProvider' => $effectiveProvider,
            'model' => $model ?? ($editingConnection?->getModel() ?? ($matchesSaved ? $configuration->modelName($workspace) : '')),
            'localEndpoint' => $localEndpoint ?? $editingConnection?->getLocalEndpoint() ?? 'http://host.docker.internal:11434/v1',
            'hasSavedKey' => ($editingConnection?->hasApiKey() ?? false) && $configuration->isConnectionConfigured($editingConnection),
            'configured' => $effectiveConfiguration,
            'ready' => $ready,
            'canTest' => $matchesSaved && $effectiveConfiguration,
            'unsavedSelection' => !$matchesSaved,
            'testedAt' => $matchesSaved ? $setting?->getTestedAt() : null,
            'lastTestLatencyMs' => $matchesSaved ? $setting?->getLastTestLatencyMs() : null,
            'disclosure' => $disclosure,
            'providerDefinition' => $definition,
            'providerRequiresKey' => $definition['requiresKey'],
            'formError' => $formError,
            'formErrorField' => $formErrorField,
            'connections' => $connectionRows,
            'savedProviders' => $savedProviders,
            'savedConnectionData' => $savedConnectionData,
        ]);
    }
}
