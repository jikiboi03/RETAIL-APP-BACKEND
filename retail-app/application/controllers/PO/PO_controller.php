<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class PO_controller extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->model('PO/PO_model','po');
        $this->load->model('PO_details/PO_details_model','po_details');
        $this->load->model('PO_temp/PO_temp_model','po_temp');
        $this->load->model('Items/Items_model','items');
    }

    public function index()						
    {
        if($this->session->userdata('administrator') == '0')
        {
            redirect('error500');
        }

        $this->load->helper('url');							

        $data['title'] = '<i class="fa fa-cart-plus"></i> Purchase Oders';					
        $this->load->view('template/dashboard_header',$data);
        $this->load->view('po/po_view',$data);
        $this->load->view('template/dashboard_navigation');
        $this->load->view('template/dashboard_footer');

    }
   
    public function ajax_list()
    {
        $list = $this->po->get_datatables();
        $data = array();
        $no = $_POST['start'];
        foreach ($list as $po) {
            $no++;
            $row = array();
            $row[] = 'PO' . $po->po_id;
            $row[] = '<b>' . $po->supplier_name . '</b>';
            $row[] = $po->username;
            
            $row[] = $po->date;

            $row[] = $po->status;
            $row[] = $po->encoded;

            //add html for action
            $row[] = '<a class="btn btn-sm btn-primary" href="javascript:void(0)" title="View" onclick="view_po('."'".$po->po_id."'".')"><i class="fa fa-eye"></i> </a>';
 
            $data[] = $row;
        }
 
        $output = array(
                        "draw" => $_POST['draw'],
                        "recordsTotal" => $this->po->count_all(),
                        "recordsFiltered" => $this->po->count_filtered(),
                        "data" => $data,
                );
        //output to json format
        echo json_encode($output);
    }
 
    public function ajax_edit($po_id)
    {
        $data = $this->po->get_by_id($po_id);
        echo json_encode($data);
    }
 
    // create/generate PO function
    public function ajax_add()
    {
        $this->_validate();
        $data = array(
                'supplier_id' => $this->input->post('supplier_id'),
                'user_id' => $this->session->userdata('user_id'),
                'date' => $this->input->post('date'),
                'status' => 'PENDING',
            );
        $insert = $this->po->save($data);
        
        $po_temp_items = $this->po_temp->get_po_temp_items();

        foreach ($po_temp_items as $po_temp_item)
        {
            // insert po item to po_details from po_temp_details ------------------------
            $data_po_items = array(
                'po_id' => $insert,
                'prod_id' => $po_temp_item->prod_id,
                'unit_qty' => $po_temp_item->unit_qty,
                'unit' => $po_temp_item->unit,
                'arrived_qty' => 0
            );
            $this->po_details->save($data_po_items);
        }
        $this->po_temp->truncate_table();

        echo json_encode(array("status" => TRUE, "po_id" => $insert));
    }

    public function ajax_complete()
    {
        $this->_validate_set();

        $po_id = $this->input->post('po_id');
        $data = array(
            'status' => 'COMPLETED'
        );
        $this->po->update(array('po_id' => $po_id), $data);
        
        $po_items = $this->po_details->get_po_items($po_id);

        foreach ($po_items as $po_item)
        {
            $prod_id = $po_item->prod_id;
            $arrived_qty = $po_item->arrived_qty;

            $this->items->update_stock_in($prod_id, $arrived_qty);
        }

        echo json_encode(array("status" => TRUE));
    }

    public function ajax_cancel()
    {
        // $this->_validate();

        $po_id = $this->input->post('po_id');
        $data = array(
            'status' => 'CANCELLED'
        );
        $this->po->update(array('po_id' => $po_id), $data);
        
        echo json_encode(array("status" => TRUE));
    }
 
    public function ajax_update()
    {
        $data = array(
            'supplier_id' => $this->input->post('supplier_id'),
            'date' => $this->input->post('date')
        );
        $this->po->update(array('po_id' => $this->input->post('po_id')), $data);
        echo json_encode(array("status" => TRUE));
    }

    // delete a po
    // public function ajax_delete($po_id)
    // {
    //     $data = array(
    //             'removed' => 1
    //         );
    //     $this->po->update(array('po_id' => $po_id), $data);
    //     echo json_encode(array("status" => TRUE));
    // }

    private function _validate()
    {
        $data = array();
        $data['error_string'] = array();
        $data['inputerror'] = array();
        $data['status'] = TRUE;

        if($this->input->post('supplier_id') == '')
        {
            $data['inputerror'][] = 'supplier_id';
            $data['error_string'][] = 'Purchase order supplier is required';
            $data['status'] = FALSE;
        }

        if($this->input->post('date') == '')
        {
            $data['inputerror'][] = 'date';
            $data['error_string'][] = 'Date is required';
            $data['status'] = FALSE;
        }

        $no_entry = $this->po_temp->get_no_entry();
        if($no_entry->num_rows() == 0)
        {
            $data['inputerror'][] = 'generate';
            $data['error_string'][] = 'PO should contain entries';
            $data['status'] = FALSE;
        }

        $no_quantity = $this->po_temp->get_no_quantity();
        if($no_quantity->num_rows() != 0)
        {
            $data['inputerror'][] = 'generate';
            $data['error_string'][] = 'Quantities should contain values';
            $data['status'] = FALSE;
        }

        if($data['status'] === FALSE)
        {
            echo json_encode($data);
            exit();
        }
    }

    private function _validate_set()
    {
        $data = array();
        $data['error_string'] = array();
        $data['inputerror'] = array();
        $data['status'] = TRUE;

        $po_id = $this->input->post('po_id');
        if($this->input->post('supplier_id') == '')
        {
            $data['inputerror'][] = 'supplier_id';
            $data['error_string'][] = 'Purchase order supplier is required';
            $data['status'] = FALSE;
        }

        if($this->input->post('date') == '')
        {
            $data['inputerror'][] = 'date';
            $data['error_string'][] = 'Date is required';
            $data['status'] = FALSE;
        }

        $no_entry = $this->po_details->get_no_entry($po_id);
        $no_quantity = $this->po_details->get_no_quantity($po_id);
        if($no_entry->num_rows() == 0)
        {
            $data['inputerror'][] = 'generate';
            $data['error_string'][] = 'PO should contain entries';
            $data['status'] = FALSE;
        }
        else if ($no_quantity->num_rows() == 0)
        {
            $data['inputerror'][] = 'generate';
            $data['error_string'][] = 'Arrived quantity should contain values';
            $data['status'] = FALSE;
        }

        if($data['status'] === FALSE)
        {
            echo json_encode($data);
            exit();
        }
    }



    // ================================================ API GET REQUEST METHOD ============================================


    public function ajax_api_list()
    {
        $list = $this->po->get_api_datatables();
        $data = array();
        
        foreach ($list as $po) {

            $row = array();
            $row['po_id'] = $po->po_id;
            $row['supplier_id'] = $po->supplier_id;
            $row['supplier_name'] = $po->supplier_name;
            $row['user_id'] = $po->user_id;
            $row['username'] = $po->username;
            
            $row['date'] = $po->date;
            $row['status'] = $po->status;

            $row['encoded'] = $po->encoded;

            $data[] = $row;
        }
    
        //output to json format
        echo json_encode($data);
    }


    // ================================================ API POST REQUEST METHOD ============================================
 }