<?php

namespace Nilesh\Customerimport\Console\Command;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\App\State;
use Magento\Framework\File\Csv;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CustomerImportCommand extends Command
{
    /**
     * @var State
     */
    protected $state;

    /**
     * @var CustomerInterfaceFactory
     */
    protected $customerFactory;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var JsonHelper
     */
    protected $jsonHelper;

    /**
     * @var Csv
     */
    protected $csvProcessor;

    /**
     * @var File
     */
    protected $file;

    /**
     * CustomerImportCommand constructor.
     * @param State $state
     * @param CustomerInterfaceFactory $customerFactory
     * @param CustomerRepositoryInterface $customerRepository
     * @param JsonHelper $jsonHelper
     * @param Csv $csvProcessor
     * @param File $file
     */
    public function __construct(
        State $state,
        CustomerInterfaceFactory $customerFactory,
        CustomerRepositoryInterface $customerRepository,
        JsonHelper $jsonHelper,
        Csv $csvProcessor,
        File $file
    ) {
        parent::__construct();
        $this->state = $state;
        $this->customerFactory = $customerFactory;
        $this->customerRepository = $customerRepository;
        $this->jsonHelper = $jsonHelper;
        $this->csvProcessor = $csvProcessor;
        $this->file = $file;
    }

    /**
     *  Console command config
     */
    protected function configure()
    {
        $this->setName('customer:import')
            ->setDescription('Import customers and their addresses from CSV or JSON')
            ->addArgument('profile-name', InputArgument::REQUIRED, 'Profile name')
            ->addArgument('source', InputArgument::REQUIRED, 'Source file path');
    }

    /**
     * Execute console command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\State\InputMismatchException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $profileName = $input->getArgument('profile-name');
            $source = $input->getArgument('source');

            // Implement import logic here
            $output->writeln("Importing customers and their addresses from profile: {$profileName}, source: {$source}");

            $sourceFileType = $this->file->getPathInfo($source)['extension'];

            if (!in_array($sourceFileType, ['csv', 'json'])) {
                $output->writeln('<error>Unsupported file format. Please provide a CSV or JSON file.</error>');
                return \Magento\Framework\Console\Cli::RETURN_FAILURE;
            }
            // Read data from CSV or JSON file and process accordingly
            $customersData = $this->readFile($source, $sourceFileType);

            foreach ($customersData as $customerData) {
                $email = $customerData['emailaddress'];
                // Check if customer already exists
                $customerExist = $this->getCustomerByEmail($email);
                if ($customerExist && $customerExist->getId()) {
                    $output->writeln("Customer {$email} already exists. Skipping...");
                } else {
                    $customer = $this->customerFactory->create();
                    $customer->setWebsiteId(1);
                    $customer->setGroupId(1);
                    $customer->setFirstname($customerData['fname']);
                    $customer->setLastname($customerData['lname']);
                    $customer->setEmail($customerData['emailaddress']);
                    $this->customerRepository->save($customer);
                }
            }

            $output->writeln('Import process completed.');
            return \Magento\Framework\Console\Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            // Handle exceptions and errors
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }
    }

    /**
     * Read CSV and Json File
     *
     * @param $source
     * @param $sourceFileType
     * @return array|mixed
     * @throws \Exception
     */
    protected function readFile($source, $sourceFileType)
    {
        if ($sourceFileType === 'csv') {
            $csvcData = $this->csvProcessor->getData($source);
            $keys = array_shift($csvcData);
            foreach ($csvcData as $row) {
                $data[] = array_combine($keys, $row);
            }
        } elseif ($sourceFileType === 'json') {
            $jsonData = file_get_contents($source);
            $data = $this->jsonHelper->jsonDecode($jsonData, true);
        }
        return $data;
    }
    
    public function getCustomerByEmail($email)
    {
        try {
            return $this->customerRepository->get($email);
        } catch (\Exception $e) {
            return false;
        }
    }
}
