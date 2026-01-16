<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Schema;

use InvalidArgumentException;
use Nevay\OTelSDK\Common\Attributes;
use Nevay\OTelSDK\Common\Resource;
use function array_key_exists;
use function array_reverse;
use function assert;
use function count;
use function sprintf;
use function strrpos;
use function substr;
use function substr_compare;
use function version_compare;

/**
 * @experimental
 */
final class StaticResourceTransformer implements ResourceTransformer {

    /**
     * @param array<string, int> $versions
     * @param list<array<string, string>> $attributeMaps
     */
    private function __construct(
        private readonly string $schemaUrl,
        private readonly array $versions,
        private readonly array $attributeMaps,
    ) {}

    /**
     * Returns a resource transformer for the `https://opentelemetry.io/schemas/` schema family.
     *
     * @see https://opentelemetry.io/docs/specs/semconv/resource/
     */
    public static function opentelemetrySchema(): ResourceTransformer {
        return self::https_opentelemetry_io_schemas_1_39_0();
    }

    /**
     * Returns a resource transformer for the given schema file.
     *
     * @param array{
     *     file_format: string,
     *     schema_url: string,
     *     versions: array<string, array{
     *         all?: array{
     *             changes: list<array{
     *                 rename_attributes?: array{
     *                     attribute_map: array<string, string>,
     *                 },
     *             }>,
     *         },
     *         resources?: array{
     *             changes: list<array{
     *                 rename_attributes?: array{
     *                     attribute_map: array<string, string>,
     *                 },
     *             }>,
     *         },
     *         ...
     *     }>,
     * } $schema
     *
     * @see https://opentelemetry.io/docs/specs/otel/schemas/file_format_v1.1.0/
     */
    public static function fromSchema(array $schema): ResourceTransformer {
        if (version_compare('1.1.0', $schema['file_format']) < 0) {
            throw new InvalidArgumentException('Unsupported file format version, expected <= 1.1.0');
        }

        $schemaUrl = $schema['schema_url'];

        $versions = [];
        $attributeMaps = [];
        foreach (array_reverse($schema['versions']) as $version => $data) {
            foreach ($data['all']['changes'] ?? [] as $change) {
                if ($attributeMap = $change['rename_attributes']['attribute_map'] ?? []) {
                    $attributeMaps[] = $attributeMap;
                }
            }
            foreach ($data['resources']['changes'] ?? [] as $change) {
                if ($attributeMap = $change['rename_attributes']['attribute_map'] ?? []) {
                    $attributeMaps[] = $attributeMap;
                }
            }

            $versions[$version] = count($attributeMaps);
        }

        return new self($schemaUrl, $versions, $attributeMaps);
    }

    public function transformResource(Resource $resource, string $schemaUrl): Resource {
        if ($resource->schemaUrl === null || $resource->schemaUrl === $schemaUrl) {
            return $resource;
        }

        if (!self::schemaFamilyMatches($resource->schemaUrl, $schemaUrl)) {
            throw new TransformationException(sprintf('Cannot transform to different schema family (%s -> %s)', $schemaUrl, $resource->schemaUrl));
        }
        if (!self::schemaFamilyMatches($schemaUrl, $this->schemaUrl)) {
            throw new TransformationException(sprintf('Unsupported schema family (%s), supported: %s', $schemaUrl, $this->schemaUrl));
        }

        $fromVersion = self::schemaVersion($resource->schemaUrl);
        $toVersion = self::schemaVersion($schemaUrl);

        $fromIndex = $this->versions[$fromVersion] ?? null;
        $toIndex = $this->versions[$toVersion] ?? null;

        if ($fromIndex === null || $toIndex === null) {
            throw new TransformationException(sprintf('Unsupported schema version (%s -> %s)', $fromVersion, $toVersion));
        }

        $attributes = $resource->attributes->toArray();

        for ($i = $fromIndex; $i < $toIndex; $i++) {
            $attributes = self::transformAttributes($attributes, $this->attributeMaps[$i]);
        }
        for ($i = $fromIndex; --$i >= $toIndex;) {
            $attributes = self::transformAttributes($attributes, $this->attributeMaps[$i], true);
        }

        return new Resource(
            attributes: new Attributes($attributes, $resource->attributes->getDroppedAttributesCount()),
            schemaUrl: $schemaUrl,
        );
    }

    private static function transformAttributes(array $attributes, array $attributeMap, bool $reverse = false): array {
        $transformations = [];
        foreach ($attributeMap as $from => $to) {
            if ($reverse) {
                [$from, $to] = [$to, $from];
            }

            if (!array_key_exists($from, $attributes)) {
                continue;
            }
            if (array_key_exists($to, $attributes) && $attributes[$from] !== $attributes[$to]) {
                throw new TransformationException(sprintf('Transformed attribute conflicts with existing attribute (%s -> %s)', $from, $to));
            }
            if (array_key_exists($from, $transformations) && assert($reverse)) {
                throw new TransformationException(sprintf('Ambiguous transformation (%s -> %s/%s)', $from, $transformations[$from], $to));
            }

            $transformations[$from] = $to;
        }

        if (!$transformations) {
            return $attributes;
        }

        $mappedAttributes = [];
        foreach ($attributes as $name => $value) {
            $mappedAttributes[$transformations[$name] ?? $name] = $value;
        }

        return $mappedAttributes;
    }

    private static function schemaFamilyMatches(string $left, string $right): bool {
        $lp = strrpos($left, '/');
        $rp = strrpos($right, '/');

        return $lp !== false
            && $rp !== false
            && $lp === $rp
            && !substr_compare($left, $right, 0, $lp);
    }

    private static function schemaVersion(string $schemaUrl): string {
        $separator = strrpos($schemaUrl, '/');
        if ($separator === false) {
            return '';
        }

        return substr($schemaUrl, $separator + 1);
    }

    private static function https_opentelemetry_io_schemas_1_39_0(): ResourceTransformer {
        return new StaticResourceTransformer(
            schemaUrl: 'https://opentelemetry.io/schemas/1.39.0',
            versions: [
                '1.4.0' => 0,
                '1.5.0' => 0,
                '1.6.1' => 0,
                '1.7.0' => 0,
                '1.8.0' => 0,
                '1.9.0' => 0,
                '1.10.0' => 0,
                '1.11.0' => 0,
                '1.12.0' => 0,
                '1.13.0' => 0,
                '1.14.0' => 0,
                '1.15.0' => 0,
                '1.16.0' => 0,
                '1.17.0' => 0,
                '1.18.0' => 0,
                '1.19.0' => 1,
                '1.20.0' => 1,
                '1.21.0' => 1,
                '1.22.0' => 2,
                '1.23.0' => 2,
                '1.23.1' => 2,
                '1.24.0' => 2,
                '1.25.0' => 3,
                '1.26.0' => 4,
                '1.27.0' => 9,
                '1.28.0' => 9,
                '1.29.0' => 11,
                '1.30.0' => 14,
                '1.31.0' => 15,
                '1.32.0' => 16,
                '1.33.0' => 18,
                '1.34.0' => 18,
                '1.35.0' => 19,
                '1.36.0' => 19,
                '1.37.0' => 20,
                '1.38.0' => 21,
                '1.39.0' => 22,
            ],
            attributeMaps: [
                [
                    'browser.user_agent' => 'user_agent.original',
                ],
                [
                    'telemetry.auto.version' => 'telemetry.distro.version',
                ],
                [
                    'message.type' => 'rpc.message.type',
                    'message.id' => 'rpc.message.id',
                    'message.compressed_size' => 'rpc.message.compressed_size',
                    'message.uncompressed_size' => 'rpc.message.uncompressed_size',
                ],
                [
                    'enduser.id' => 'user.id',
                ],
                [
                    'tls.client.server_name' => 'server.address',
                ],
                [
                    'deployment.environment' => 'deployment.environment.name',
                ],
                [
                    'messaging.kafka.message.offset' => 'messaging.kafka.offset',
                ],
                [
                    'messaging.kafka.consumer.group' => 'messaging.consumer.group.name',
                    'messaging.rocketmq.client_group' => 'messaging.consumer.group.name',
                    'messaging.eventhubs.consumer.group' => 'messaging.consumer.group.name',
                    'messaging.servicebus.destination.subscription_name' => 'messaging.destination.subscription.name',
                ],
                [
                    'gen_ai.usage.completion_tokens' => 'gen_ai.usage.output_tokens',
                    'gen_ai.usage.prompt_tokens' => 'gen_ai.usage.input_tokens',
                ],
                [
                    'process.executable.build_id.profiling' => 'process.executable.build_id.htlhash',
                ],
                [
                    'vcs.repository.change.id' => 'vcs.change.id',
                    'vcs.repository.change.title' => 'vcs.change.title',
                    'vcs.repository.ref.name' => 'vcs.ref.head.name',
                    'vcs.repository.ref.revision' => 'vcs.ref.head.revision',
                    'vcs.repository.ref.type' => 'vcs.ref.head.type',
                ],
                [
                    'gen_ai.openai.request.seed' => 'gen_ai.request.seed',
                    'system.network.state' => 'network.connection.state',
                ],
                [
                    'code.function' => 'code.function.name',
                    'code.filepath' => 'code.file.path',
                    'code.lineno' => 'code.line.number',
                    'code.column' => 'code.column.number',
                ],
                [
                    'db.system' => 'db.system.name',
                    'db.cassandra.coordinator.dc' => 'cassandra.coordinator.dc',
                    'db.cassandra.coordinator.id' => 'cassandra.coordinator.id',
                    'db.cassandra.consistency_level' => 'cassandra.consistency.level',
                    'db.cassandra.idempotence' => 'cassandra.query.idempotent',
                    'db.cassandra.page_size' => 'cassandra.page.size',
                    'db.cassandra.speculative_execution_count' => 'cassandra.speculative_execution.count',
                    'db.cosmosdb.client_id' => 'azure.client.id',
                    'db.cosmosdb.connection_mode' => 'azure.cosmosdb.connection.mode',
                    'db.cosmosdb.consistency_level' => 'azure.cosmosdb.consistency.level',
                    'db.cosmosdb.request_charge' => 'azure.cosmosdb.operation.request_charge',
                    'db.cosmosdb.request_content_length' => 'azure.cosmosdb.request.body.size',
                    'db.cosmosdb.regions_contacted' => 'azure.cosmosdb.operation.contacted_regions',
                    'db.cosmosdb.sub_status_code' => 'azure.cosmosdb.response.sub_status_code',
                    'db.elasticsearch.node.name' => 'elasticsearch.node.name',
                ],
                [
                    'android.state' => 'android.app.state',
                    'io.state' => 'ios.app.state',
                ],
                [
                    'feature_flag.evaluation.reason' => 'feature_flag.result.reason',
                    'feature_flag.variant' => 'feature_flag.result.variant',
                ],
                [
                    'feature_flag.provider_name' => 'feature_flag.provider.name',
                ],
                [
                    'feature_flag.evaluation.error.message' => 'error.message',
                ],
                [
                    'az.namespace' => 'azure.resource_provider.namespace',
                    'az.service_request_id' => 'azure.service.request.id',
                ],
                [
                    'android.state' => 'android.app.state',
                    'container.runtime' => 'container.runtime.name',
                    'enduser.role' => 'user.roles',
                    'gen_ai.openai.request.service_tier' => 'openai.request.service_tier',
                    'gen_ai.openai.response.service_tier' => 'openai.response.service_tier',
                    'gen_ai.openai.response.system_fingerprint' => 'openai.response.system_fingerprint',
                    'gen_ai.system' => 'gen_ai.provider.name',
                    'ios.state' => 'ios.app.state',
                ],
                [
                    'process.context_switch_type' => 'process.context_switch.type',
                    'process.paging.fault_type' => 'system.paging.fault.type',
                    'system.cpu.logical_number' => 'cpu.logical_number',
                    'system.paging.type' => 'system.paging.fault.type',
                    'system.process.status' => 'process.state',
                    'system.processes.status' => 'process.state',
                ],
                [
                    'linux.memory.slab.state' => 'system.memory.linux.slab.state',
                    'peer.service' => 'service.peer.name',
                    'rpc.connect_rpc.error_code' => 'rpc.response.status_code',
                    'rpc.connect_rpc.request.metadata' => 'rpc.request.metadata',
                    'rpc.connect_rpc.response.metadata' => 'rpc.response.metadata',
                    'rpc.grpc.request.metadata' => 'rpc.request.metadata',
                    'rpc.grpc.response.metadata' => 'rpc.response.metadata',
                    'rpc.jsonrpc.request_id' => 'jsonrpc.request.id',
                    'rpc.jsonrpc.version' => 'jsonrpc.protocol.version',
                    'rpc.system' => 'rpc.system.name',
                ],
            ],
        );
    }
}
