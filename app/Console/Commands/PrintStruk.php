<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use charlieuki\ReceiptPrinter\Item as Item;
use charlieuki\ReceiptPrinter\Store as Store;
use Mike42\Escpos\Printer;
use Mike42\Escpos\CapabilityProfile;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\CupsPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;

class PrintStruk extends Command
{
    private $currency = 'Rp';
    private $printer;
    private $left_cols = 22;
    private $right_cols = 20;
    private $dw_left_cols = 16;
    private $dw_right_cols = 16;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'print:struck';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'PrintStruck';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(){
        $response = Http::get(env("API_URL"), ["userId" => env("USER_ID")]);
        if($response->status() == 200){
            $dt = $response->json();
            if($dt){
                $store_name = $dt["store_name"];
                $store_address = $dt["store_address"];
                $store_phone = $dt["store_phone"];
                $this->currency = $dt["currency"];
                $connector_type = config('receiptprinter.connector_type');
                $connector_descriptor = config('receiptprinter.connector_descriptor');
                switch (strtolower($connector_type)) {
                    case 'cups':
                        $connector = new CupsPrintConnector($connector_descriptor);
                        break;
                    case 'windows':
                        $connector = new WindowsPrintConnector($connector_descriptor);
                        break;
                    case 'network':
                        $connector = new NetworkPrintConnector($connector_descriptor);
                        break;
                    default:
                        $connector = new FilePrintConnector("php://stdout");
                        break;
                }
                if ($connector) {
                    $profile = CapabilityProfile::load("default");
                    $this->printer = new Printer($connector, $profile);
                } else {
                    throw new Exception('Invalid printer connector type. Accepted values are: cups');
                }
                $footer = "Cashier : " . $dt["cashier_name"] . "\nThank you for shopping!";
                foreach($dt["data"] as $data){
                    $subtotal = $this->getPrintableSummary('Subtotal', $data["subtotal"]);
                    $discount = $this->getPrintableSummary('Discount', $data["discount"]);
                    $total = $this->getPrintableSummary('Total', $data["subtotal"], true);
                    $paid = $this->getPrintableSummary('Paid', $data["paid_amount"]);    
                    $change = $this->getPrintableSummary('Change', $data["change_amount"], true);    

                    $this->printer->initialize();
                    $this->printer->setFont(Printer::FONT_B);
                    // set header
                    $this->printer->setJustification(Printer::JUSTIFY_CENTER);
                    $this->printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
                    // $this->printer->feed(2);
                    $this->printer->text("{$store_name}\n");
                    $this->printer->feed();
                    $this->printer->selectPrintMode(Printer::MODE_FONT_B);
                    $this->printer->text("{$store_address}\n");
                    $this->printer->text("Phone : " . $store_phone . "\n");
                    $this->printer->setEmphasis(true);
                    $this->printer->text("TID : " . $data["id"]);
                    $this->printer->setEmphasis(false);
                    $this->printer->feed(2);
                    // Print receipt title
                    $this->printer->setEmphasis(true);
                    $this->printer->text("RECEIPT");
                    $this->printer->setEmphasis(false);
                    $this->printer->feed();
                    // Print items
                    $this->printer->setJustification(Printer::JUSTIFY_LEFT);
                    foreach ($data["items"] as $item) {
                        $item_price = $this->currency. " " . number_format($item["price"], 0, ',', '.');
                        $item_subtotal = $this->currency. " " . number_format($item["price"] * $item["qty"], 0, ',', '.');
                        $item_discount = "-" . $this->currency. " " . number_format($item["discount"], 0, ',', '.');
                        
                        $print_name = str_pad($item["name"], 16) ;
                        $print_priceqty = str_pad($item_price . ' x ' . $item["qty"], $this->left_cols);
                        $print_subtotal = str_pad($item_subtotal, $this->right_cols, ' ', STR_PAD_LEFT);

                        $itemdiscount = $data["discount"] == 0 ? "" : "\n" . str_pad("Discount", $this->left_cols) . str_pad($item_discount, $this->right_cols, ' ', STR_PAD_LEFT);

                        $textItem = "$print_name\n$print_priceqty$print_subtotal" . $itemdiscount;
                        $this->printer->text($textItem);
                        $this->printer->feed();
                    }
                    // Print subtotal
                    $this->printer->feed();
                    $this->printer->text($subtotal);
                    $this->printer->feed();
                    // Print Discount
                    $this->printer->text($discount);
                    $this->printer->feed();
                    // Print total
                    $this->printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                    $this->printer->setEmphasis(true);
                    $this->printer->text($total);
                    $this->printer->setEmphasis(false);
                    $this->printer->feed();
                    // Print Paid
                    $this->printer->selectPrintMode(Printer::MODE_FONT_B);
                    $this->printer->text($paid);
                    $this->printer->feed();
                    // Print Change
                    $this->printer->selectPrintMode(Printer::MODE_EMPHASIZED);
                    $this->printer->setEmphasis(true);
                    $this->printer->text($change);
                    $this->printer->setEmphasis(false);
                    $this->printer->feed(2);
                    
                    
                    $this->printer->setJustification(Printer::JUSTIFY_CENTER);
                    $this->printer->selectPrintMode(Printer::MODE_FONT_B);
                    
                    $this->printer->text($footer);
                    $this->printer->feed();
                    // Print receipt date
                    $this->printer->text(date('j F Y H:i:s'));
                    $this->printer->feed();
                    // Cut the receipt*/
                    $this->printer->cut();
                }
                $this->printer->close();
            }
        }
        return 0;
    }

    public function getPrintableSummary($label, $value, $is_double_width = false) {
        $left_cols = $is_double_width ? $this->dw_left_cols : $this->left_cols;
        $right_cols = $is_double_width ? $this->dw_right_cols : $this->right_cols;

        $formatted_value = $this->currency . " " . number_format($value, 0, ',', '.');

        return str_pad($label, $left_cols) . str_pad($formatted_value, $right_cols, ' ', STR_PAD_LEFT);
    }
    public function __destruct(){
        if($this->printer){
            $this->printer->close();
        }   
    }
}
