<?php
namespace GoetasWebservices\WsdlToPhp\Tests;

use PHPUnit\Framework\TestCase;

class ValidatorGenerationTest extends TestCase
{
    /**
     * @var Generator
     */
    protected static $generator;
    protected static $validation = [];
    private static $namespace = 'Ex';

    public static function setUpBeforeClass(): void
    {
        self::$generator = new Generator([
            'http://www.example.org/test/' => self::$namespace
        ]);

        list(, , self::$validation) = self::$generator->getData([__DIR__ . '/../Fixtures/test.wsdl']);
    }

    /**
     * Each yielded item is keyed by its own class name (matching JMSWriter's
     * expectations: it dumps the item as-is, so it must already be shaped as
     * ClassName => data), and the outer $items array key is the same string -
     * redundant, but that's the existing SoapConverter::getTypes() convention.
     */
    private function propertiesOf($className)
    {
        $this->assertArrayHasKey($className, self::$validation);
        $item = self::$validation[$className];
        $this->assertArrayHasKey($className, $item);

        return $item[$className]['properties'];
    }

    public function testMessagePartsClassGetsNotNullAndValid()
    {
        // "getSimple" has a single body part -> the Parts wrapper property must
        // require it (structurally always present when the message occurs) and
        // cascade validation into its own schema-derived rules.
        $properties = $this->propertiesOf('Ex\SoapParts\GetSimpleOutput');

        $this->assertSame([
            ['NotNull' => null],
            ['Valid' => null],
        ], $properties['getSimpleResponse']);
    }

    public function testEnvelopeClassRequiresBodyButNotHeader()
    {
        $properties = $this->propertiesOf('Ex\SoapEnvelope\Messages\GetSimpleOutput');

        $this->assertSame([
            ['NotNull' => null],
            ['Valid' => null],
        ], $properties['body']);

        // A SOAP header is optional by convention: only cascade, never require it.
        $this->assertSame([
            ['Valid' => null],
        ], $properties['header']);
    }

    public function testEnvelopeClassWithoutBodyPartsHasNoBodyRules()
    {
        // "noBoth"-style messages with an empty body must not force NotNull on a
        // body that structurally never carries anything.
        $properties = $this->propertiesOf('Ex\SoapEnvelope\Messages\NoBothInput');

        $this->assertSame([], $properties['body']);
    }

    public function testUnderlyingSchemaTypeStillGetsItsOwnFieldRules()
    {
        // The field-level rules for the actual message content ("out" is a
        // required xsd:string with no minOccurs, i.e. minOccurs defaults to 1)
        // come from the underlying YamlValidatorConverter walking the WSDL schema
        // directly - proving visitElementDef()/visitType() delegation actually
        // registers the class, not just the thin wsdl2php wrapper. Unlike the
        // wsdl2php-specific classes above, this one goes through xsd2php's own
        // YamlConverter::getTypes(), which already returns the flat shape.
        $properties = $this->propertiesOf('Ex\GetSimpleResponse\GetSimpleResponseAType');
        $this->assertSame([
            ['NotNull' => ['groups' => ['xsd_rules']]],
        ], $properties['out']);
    }
}
