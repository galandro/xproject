<?php
/**
 * Geo POS -  Accounting,  Invoicing  and CRM Application
 * Copyright (c) Rajesh Dukiya. All Rights Reserved
 * ***********************************************************************
 *
 *  Email: support@ultimatekode.com
 *  Website: https://www.ultimatekode.com
 *
 *  ************************************************************************
 *  * This software is furnished under a license and may be used and copied
 *  * only  in  accordance  with  the  terms  of such  license and with the
 *  * inclusion of the above copyright notice.
 *  * If you Purchased from Codecanyon, Please read the full License from
 *  * here- http://codecanyon.net/licenses/standard/
 * ***********************************************************************
 */

defined('BASEPATH') OR exit('No direct script access allowed');

class Export extends CI_Controller
{
    var $date;

    public function __construct()
    {
        parent::__construct();
        $this->load->library("Aauth");
        $this->load->model('export_model', 'export');
        if (!$this->aauth->is_loggedin()) {
            redirect('/user/', 'refresh');
            exit;
        }

        if ($this->aauth->get_user()->roleid < 5) {

            exit('Not Allowed!');
        }
        $this->date = 'backup_' . date('Y_m_d_H_i_s');
        $this->li_a = 'export';


    }


    function dbexport()
    {


        $head['title'] = "Backup Database";
        $head['usernm'] = $this->aauth->get_user()->username;
        $this->load->view('fixed/header', $head);
        $this->load->view('export/db_back');
        $this->load->view('fixed/footer');


    }


    function dbexport_c()
    {

        $this->load->dbutil();
        $backup =& $this->dbutil->backup();
        $this->load->helper('file');
        write_file('<?php  echo base_url();?>/downloads', $backup);
        $this->load->helper('download');
        force_download($this->date . '.gz', $backup);
    }


    function crm()
    {


        $head['title'] = "Export CRM Data";
        $head['usernm'] = $this->aauth->get_user()->username;
        $this->load->view('fixed/header', $head);
        $this->load->view('export/crm');
        $this->load->view('fixed/footer');


    }


    function crm_now()
    {


        $type = $this->input->post('type');

        switch ($type) {
            case 1 :
                $this->customers();
                break;

            case 2 :
                $this->suppliers();
                break;
        }


    }

    private function customers()
    {

        $this->load->dbutil();
        $this->load->helper('file');
        $this->load->helper('download');
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=customers_' . $this->date . '..csv');
        header('Content-Transfer-Encoding: binary');
        $whr = '';
        if ($this->aauth->get_user()->loc) {
            $whr = " WHERE loc='" . $this->aauth->get_user()->loc . "';";
        } elseif (!BDATA) {
            $whr = " WHERE loc='0';";
        }


        $query = $this->db->query("SELECT name,address,city,region,country,postbox,email,phone,company FROM geopos_customers $whr");
        echo "\xEF\xBB\xBF"; // Byte Order Mark
        echo $this->dbutil->csv_from_result($query);
        //  force_download('customers_' . $this->date . '.csv', );

    }

    private function suppliers()
    {
        $whr = '';
        if ($this->aauth->get_user()->loc) {
            $whr = " WHERE loc='" . $this->aauth->get_user()->loc . "';";
        } elseif (!BDATA) {
            $whr = " WHERE loc='0';";
        }
        $query = $this->db->query("SELECT name,address,city,region,country,postbox,email,phone,company FROM geopos_supplier $whr");
        $this->load->dbutil();
        $this->load->helper('file');
        $this->load->helper('download');
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=suppliers_' . $this->date . '..csv');
        header('Content-Transfer-Encoding: binary');
        echo "\xEF\xBB\xBF"; // Byte Order Mark
        echo $this->dbutil->csv_from_result($query);

    }

    function transactions()
    {
        $this->load->model('transactions_model');
        $data['accounts'] = $this->transactions_model->acc_list();
        $head['title'] = "Export Transactions";
        $head['usernm'] = $this->aauth->get_user()->username;
        $this->load->view('fixed/header', $head);
        $this->load->view('export/transactions', $data);
        $this->load->view('fixed/footer');
    }

    function transactions_o()
    {
        $whr = '';
        if ($this->aauth->get_user()->loc) {
            $whr = " AND loc='" . $this->aauth->get_user()->loc . "';";
        } elseif (!BDATA) {
            $whr = " AND loc='0';";
        }

        $pay_acc = $this->input->post('pay_acc');
        $trans_type = $this->input->post('trans_type');
        $sdate = datefordatabase($this->input->post('sdate'));
        $edate = datefordatabase($this->input->post('edate'));
        if ($pay_acc == 'All') {
            if ($trans_type == 'All') {
                $where = " WHERE (DATE(date) BETWEEN '$sdate' AND '$edate') ";
            } else {
                $where = " WHERE (DATE(date) BETWEEN '$sdate' AND '$edate') AND type='$trans_type'";
            }
        } else {
            if ($trans_type == 'All') {
                $where = " WHERE acid='$pay_acc' AND (DATE(date) BETWEEN '$sdate' AND '$edate') ";
            } else {
                $where = " WHERE acid='$pay_acc' AND (DATE(date) BETWEEN '$sdate' AND '$edate') AND type='$trans_type'";
            }
        }

        $this->load->dbutil();
        $this->load->helper('file');
        $this->load->helper('download');
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=transactions_' . $this->date . '..csv');
        header('Content-Transfer-Encoding: binary');
        $query = $this->db->query("SELECT account,type,cat AS category,debit,credit,payer,method,date,note FROM geopos_transactions" . $where . ' ' . $whr);
        echo "\xEF\xBB\xBF"; // Byte Order Mark
        echo $this->dbutil->csv_from_result($query);
    }


    function products()
    {
        $head['title'] = "Export Products";
        $head['usernm'] = $this->aauth->get_user()->username;
        $this->load->view('fixed/header', $head);
        $this->load->view('export/products');
        $this->load->view('fixed/footer');
    }

    function products_o()
    {
        $whr = '';
        if ($this->aauth->get_user()->loc) {
            $whr = "LEFT JOIN geopos_warehouse ON geopos_products.warehouse=geopos_warehouse.id WHERE geopos_warehouse.loc='" . $this->aauth->get_user()->loc . "';";
        } elseif (!BDATA) {
            $whr = "LEFT JOIN geopos_warehouse ON geopos_products.warehouse=geopos_warehouse.id WHERE geopos_warehouse.loc='0';";
        }

        $type = $this->input->post('type');
        $query = '';
        switch ($type) {
            case 1 :
                $query = "SELECT product_name,product_code,product_price,fproduct_price AS factory_price,taxrate,disrate AS discount_rate,qty FROM geopos_products $whr";
                break;

            case 2 :
                $query = "SELECT geopos_product_cat.title as category,geopos_products.product_name,geopos_products.product_code,geopos_products.product_price,geopos_products.fproduct_price AS factory_price,geopos_products.taxrate,geopos_products.disrate AS discount_rate,geopos_products.qty FROM geopos_products LEFT JOIN geopos_product_cat ON geopos_products.pcat=geopos_product_cat.id $whr";
                break;
        }
        $query = $this->db->query($query);
        $this->load->dbutil();
        $this->load->helper('file');
        $this->load->helper('download');
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=transactions_' . $this->date . '..csv');
        header('Content-Transfer-Encoding: binary');


        echo "\xEF\xBB\xBF"; // Byte Order Mark
        echo $this->dbutil->csv_from_result($query);

    }


    function account()
    {


        $this->load->model('transactions_model');
        $data['accounts'] = $this->transactions_model->acc_list();
        $head['title'] = "Export Transactions";
        $head['usernm'] = $this->aauth->get_user()->username;
        $this->load->view('fixed/header', $head);
        $this->load->view('export/account', $data);
        $this->load->view('fixed/footer');


    }

    function accounts_o()
    {
        $this->load->model('reports_model');
        $this->load->model('accounts_model');

        $pay_acc = $this->input->post('pay_acc');
        $trans_type = $this->input->post('trans_type');
        $sdate = datefordatabase($this->input->post('sdate'));
        $edate = datefordatabase($this->input->post('edate'));
        $data['account'] = $this->accounts_model->details($pay_acc);


        $data['list'] = $this->reports_model->get_statements($pay_acc, $trans_type, $sdate, $edate);


        $html = $this->load->view('accounts/statementpdf-' . LTR, $data, true);


        ini_set('memory_limit', '64M');


        $this->load->library('pdf');

        $pdf = $this->pdf->load();


        $pdf->WriteHTML($html);


        $pdf->Output('Statement' . $pay_acc . '.pdf', 'D');


    }

    function customer()
    {
        $this->load->model('reports_model');
        $this->load->model('customers_model');

        $customer = $this->input->post('customer');
        $trans_type = $this->input->post('trans_type');
        $sdate = datefordatabase($this->input->post('sdate'));
        $edate = datefordatabase($this->input->post('edate'));
        $data['customer'] = $this->customers_model->details($customer);


        $data['list'] = $this->reports_model->get_customer_statements($customer, $trans_type, $sdate, $edate);


        $html = $this->load->view('customers/statementpdf', $data, true);


        ini_set('memory_limit', '64M');


        $this->load->library('pdf');

        $pdf = $this->pdf->load();


        $pdf->WriteHTML($html);


        $pdf->Output('Statement' . $customer . '.pdf', 'D');


    }

    function supplier()
    {
        $this->load->model('reports_model');
        $this->load->model('supplier_model');

        $customer = $this->input->post('supplier');
        $trans_type = $this->input->post('trans_type');
        $sdate = datefordatabase($this->input->post('sdate'));
        $edate = datefordatabase($this->input->post('edate'));
        $data['customer'] = $this->supplier_model->details($customer);

        $data['list'] = $this->reports_model->get_supplier_statements($customer, $trans_type, $sdate, $edate);

        $html = $this->load->view('supplier/statementpdf', $data, true);

        ini_set('memory_limit', '64M');

        $this->load->library('pdf');
        $pdf = $this->pdf->load();

        $pdf->WriteHTML($html);

        $pdf->Output('Statement' . $customer . '.pdf', 'D');


    }

    function taxstatement()
    {


        $head['title'] = "Export TAX Report";
        $head['usernm'] = $this->aauth->get_user()->username;
        $this->load->view('fixed/header', $head);
        $this->load->view('export/taxstatement');
        $this->load->view('fixed/footer');


    }

    function taxstatement_o()
    {
        $whr = '';
        $whr2 = '';
        if ($this->aauth->get_user()->loc) {
            $whr = " AND geopos_invoices.loc='" . $this->aauth->get_user()->loc . "';";
            $whr2 = " AND geopos_purchase.loc='" . $this->aauth->get_user()->loc . "';";
        } elseif (!BDATA) {
            $whr = " AND geopos_invoices.loc='0';";
            $whr2 = " AND geopos_purchase.loc='0';";
        }

        $sdate = datefordatabase($this->input->post('sdate'));
        $edate = datefordatabase($this->input->post('edate'));
        $trans_type = $this->input->post('ty');
        $prefix = $this->config->item('prefix') . '-';
        $curr = $this->config->item('currency') . ' ';

        $this->load->dbutil();
        $this->load->helper('file');
        $this->load->helper('download');
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=tax_transactions_' . $this->date . '..csv');
        header('Content-Transfer-Encoding: binary');


        echo "\xEF\xBB\xBF"; // Byte Order Mark


        if ($trans_type == 'Sales') {
            $where = " WHERE (DATE(geopos_invoices.invoicedate) BETWEEN '$sdate' AND '$edate') $whr";
            $query = $this->db->query("SELECT geopos_customers.taxid AS TAX_Number,concat('$prefix',geopos_invoices.tid) AS invoice_number,concat('$curr',geopos_invoices.total) AS amount,geopos_invoices.tax AS tax,geopos_customers.name AS customer_name,geopos_customers.company AS Company_Name,geopos_invoices.invoicedate AS date FROM geopos_invoices LEFT JOIN geopos_customers ON geopos_invoices.csd=geopos_customers.id" . $where);

            echo $this->dbutil->csv_from_result($query);

        } else {

            $where = " WHERE (DATE(geopos_purchase.invoicedate) BETWEEN '$sdate' AND '$edate') $whr";
            $query = $this->db->query("SELECT concat('$prefix',geopos_purchase.tid) AS receipt_number,concat('$curr',geopos_purchase.total) AS amount,geopos_purchase.tax AS tax,geopos_supplier.name AS supplier_name,geopos_supplier.company AS Company_Name,geopos_purchase.invoicedate AS date FROM geopos_purchase LEFT JOIN geopos_supplier ON geopos_purchase.csd=geopos_supplier.id" . $where);

            echo $this->dbutil->csv_from_result($query);

        }


    }


}