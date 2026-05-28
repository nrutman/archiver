<?php

namespace App\Controller;

use App\Archive\ArchiveCreationRequest;
use App\Archive\ArchiveCreationService;
use App\Archive\ArchiveEncryptionMode;
use App\Archive\ArchiveValidationException;
use App\TemporaryStorage\TemporaryWorkspace;
use App\TemporaryStorage\TemporaryWorkspaceFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ArchiveController extends AbstractController
{
    public function __construct(
        private readonly ArchiveCreationService $archiveCreationService,
        private readonly TemporaryWorkspaceFactory $workspaceFactory,
    ) {
    }

    /**
     * Creates a temporary ZIP archive from uploaded files and streams it back.
     */
    #[Route('/api/archives', name: 'api_archives_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $workspace = $this->workspaceFactory->create();

        try {
            $archiveRequest = $this->requestFromHttpRequest($request);
            $zipPath = $this->archiveCreationService->createArchive($archiveRequest, $workspace);

            return $this->streamArchive($zipPath, $workspace);
        } catch (ArchiveValidationException $exception) {
            $workspace->close();

            return $this->validationErrorResponse($exception->getMessage());
        } catch (\Throwable $exception) {
            $workspace->close();

            throw $exception;
        }
    }

    private function validationErrorResponse(string $message): JsonResponse
    {
        $response = new JsonResponse(null, Response::HTTP_BAD_REQUEST);
        $response->setEncodingOptions($response->getEncodingOptions() | \JSON_INVALID_UTF8_SUBSTITUTE);
        $response->setData([
            'error' => [
                'message' => $message,
            ],
        ]);

        return $response;
    }

    private function requestFromHttpRequest(Request $request): ArchiveCreationRequest
    {
        $passwordEnabled = $request->request->getBoolean('passwordEnabled');
        $encryptionMode = $passwordEnabled
            ? ArchiveEncryptionMode::fromFormValue($request->request->getString('encryptionMode', ArchiveEncryptionMode::Aes256->value))
            : ArchiveEncryptionMode::None;

        return new ArchiveCreationRequest(
            $this->uploadedFiles($request),
            $encryptionMode,
            $request->request->getString('password'),
        );
    }

    /**
     * @return list<UploadedFile>
     */
    private function uploadedFiles(Request $request): array
    {
        return $this->flattenUploadedFiles($request->files->get('files'));
    }

    /**
     * @return list<UploadedFile>
     */
    private function flattenUploadedFiles(mixed $files): array
    {
        if ($files instanceof UploadedFile) {
            return [$files];
        }

        if (!is_array($files)) {
            return [];
        }

        $uploadedFiles = [];
        foreach ($files as $file) {
            foreach ($this->flattenUploadedFiles($file) as $uploadedFile) {
                $uploadedFiles[] = $uploadedFile;
            }
        }

        return $uploadedFiles;
    }

    private function streamArchive(string $zipPath, TemporaryWorkspace $workspace): StreamedResponse
    {
        $response = new StreamedResponse(static function () use ($zipPath, $workspace): void {
            try {
                $stream = fopen($zipPath, 'rb');
                if (false === $stream) {
                    throw new \RuntimeException('Could not open the generated ZIP archive.');
                }

                try {
                    fpassthru($stream);
                } finally {
                    fclose($stream);
                }
            } finally {
                $workspace->close();
            }
        });

        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Length', (string) filesize($zipPath));
        $response->headers->set('Cache-Control', 'no-store, max-age=0');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            sprintf('archive-%s.zip', (new \DateTimeImmutable())->format('Ymd-His')),
        ));

        return $response;
    }
}
