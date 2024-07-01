<?php

declare(strict_types=1);

namespace BackgroundMessageTransport\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

#[AsCommand(name: 'backround-message-transport:consume-single-message', description: 'Consume single message')]
final class ConsumeSingleMessageCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        #[\SensitiveParameter]
        private readonly string $appSecret,
        private readonly SerializerInterface $serializer,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        parent::configure();
        $this->setDefinition([
            new InputArgument('envelope', InputArgument::REQUIRED, 'Message in envelope to consume'),
            new InputArgument('fingerprint', InputArgument::REQUIRED, 'Fingerprint of the envelope'),
        ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $envelope = null;
        $statusCode = Command::SUCCESS;

        try {
            $encodedEnvelope = (string) $input->getArgument('envelope');
            $fingerprint = (string) $input->getArgument('fingerprint');
            if (!$this->isEncodedEnvelopeValid($encodedEnvelope, $fingerprint)) {
                throw new \InvalidArgumentException('Invalid envelope');
            }

            $envelope = $this->decodeEnvelope($encodedEnvelope);
            $envelope = $this->messageBus->dispatch($envelope, [new TransportNamesStamp('background-sync')]);
        } catch (\Throwable $exception) {
            if ($envelope === null) {
                $envelope = Envelope::wrap(new \stdClass());
            }
            $envelope = $envelope->with(ErrorDetailsStamp::create($exception));
            $statusCode = Command::FAILURE;
        }

        $output->write($this->encodeEnvelope($envelope));

        return $statusCode;
    }

    private function decodeEnvelope(string $encodedEnvelope): Envelope
    {
        $decodedEnvelope = \base64_decode($encodedEnvelope);
        $envelopeData = \json_decode($decodedEnvelope, true, 512, \JSON_THROW_ON_ERROR);

        return $this->serializer->decode($envelopeData);
    }

    private function encodeEnvelope(Envelope $envelope): string
    {
        $envelopeData = $this->serializer->encode($envelope);

        return \base64_encode(\json_encode($envelopeData, \JSON_THROW_ON_ERROR));
    }

    private function isEncodedEnvelopeValid(
        string $encodedEnvelope,
        string $fingerprint
    ): bool {
        $fingerprintForEnvelope = \hash_hmac('sha256', $encodedEnvelope, $this->appSecret);

        return $fingerprint === $fingerprintForEnvelope;
    }
}
