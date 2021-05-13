<?php
/**
 * FILE EventLog.php
 *
 * @author Dark Angel <jonathanyombo@gmail.com>
 * @copyright DATE 5/13/2021 - 4:19 PM
 */

class Eventlog_Model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getAll($orderBy, $orderFormat, $start = 0, $limit = ''): ?array
    {
        $this->db->limit($limit, $start);
        $this->db->order_by($orderBy, $orderFormat);

        $run_q = $this->db->get('eventlog');

        if ($run_q->num_rows() > 0) {
            return $run_q->result();
        } else {
            return null;
        }
    }

    public function approvisionnement($orderBy, $orderFormat, $start = 0, $limit = ''): ?array
    {
        $this->db->select('eventlog.event, eventlog.eventDesc, eventlog.eventTime, admin.first_name, admin.last_name, admin.mobile1, items.name, items.code, items.description');

        $this->db->limit($limit, $start);
        $this->db->order_by('eventlog.'.$orderBy, $orderFormat);
        $this->db->join('items', 'eventlog.eventRowIdOrRef = items.id', 'LEFT');
        $this->db->join('admin', 'eventlog.staffInCharge = admin.id', 'LEFT');

        $this->db->like('event', 'Mise à jour du stock');
        $run_q = $this->db->get('eventlog');

        if ($run_q->num_rows() > 0) {
            return $run_q->result();
        } else {
            return null;
        }
    }
}
