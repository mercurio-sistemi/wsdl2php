<?php
namespace GoetasWebservices\WsdlToPhp\Command;

use GoetasWebservices\Xsd\XsdToPhp\Command\Convert as XsdToPhpConvert;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Convert extends XsdToPhpConvert
{
    protected function configure()
    {
        $this->setName('convert');
        $this->setDescription("Convert a WSDL file into PHP classes and JMS serializer metadata files");
        $this->setDefinition([
            new InputArgument('config', InputArgument::REQUIRED, 'Config file'),
            new InputArgument('src', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Where are located your WSDL files'),
        ]);
    }

    /**
     *
     * @see Console\Command\Command
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->loadConfigurations($input->getArgument('config'));

        $schemas = [];

        $wsdlReader = $this->container->get('goetas_webservices.wsdl2php.wsdl_reader');
        $src = $input->getArgument('src');
        foreach ($src as $file) {
            $definitions = $wsdlReader->readFile($file);
            $schemas[] = $definitions->getSchema();
        }

        $soapReader = $this->container->get('goetas_webservices.wsdl2php.soap_reader');

        $configs = $this->container->getParameter('goetas_webservices.xsd2php.config');

        foreach (['php', 'jms', 'validation'] as $type) {
            if ($type === 'validation' && empty($configs['destinations_' . $type])) {
                continue;
            }
            $converter = $this->container->get('goetas_webservices.xsd2php.converter.' . $type);
            $wsdlConverter = $this->container->get('goetas_webservices.wsdl2php.converter.' . $type);
            $items = $wsdlConverter->visitServices($soapReader->getServices());
            $items = array_merge($items, $converter->convert($schemas));

            $writer = $this->container->get('goetas_webservices.xsd2php.writer.' . $type);
            $writer->write($items);
        }

        return count($items) ? 0 : 255;

    }
}
