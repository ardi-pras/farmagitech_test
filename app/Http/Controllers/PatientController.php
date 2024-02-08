<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Kabupaten;

class PatientController extends Controller
{
    public function show(Request $request){

        $payload_request = $request->all();

        $validator = Validator::make($payload_request, [
            'tipe' => 'required',
            'tgl_awal' => 'required',
            'tgl_akhir' => 'required',
            'kategori' => 'required',
            'kabupaten' => 'required'
        ]);

        if($validator->fails()){
            $response = [
                "status" => "Failed",
                "details" => $validator->errors()
            ];
            $status_code = 422;
        } else {
            $response = $this->fetch_data_visit($payload_request);
            $status_code = 200;
        }

        return response()->json($response, $status_code)->header('Content-Type', 'application/json');
    }

    public function fetch_data_visit($request = array()){

        $tipe = $request["tipe"];
        $tgl_awal = $request["tgl_awal"];
        $tgl_akhir = $request["tgl_akhir"];
        $kategori = $request["kategori"];
        $kabupaten = $request["kabupaten"];

        $kab = Kabupaten::where('id', $kabupaten)->first();
        $nama_kabupaten = (!empty($kab))?$kab->nama:"Nama kabupaten tidak ditemukan.";

        $waktu = $this->tgl_indo($tgl_awal)." s.d ".$this->tgl_indo($tgl_akhir);

        $parameter= [
            "tipe"=> $tipe,
            "waktu"=> $waktu,
            "kategori"=> $kategori,
            "area"=> $nama_kabupaten
        ];

        if(strcasecmp($kategori, "Kelurahan") == 0){

            $sql_query = "SELECT * FROM (
                SELECT dc_kecamatan.nama AS kecamatan, dc_kelurahan.nama AS kelurahan, dc_pendaftaran.jenis, dc_pendaftaran.jenis_igd, COUNT(dc_pendaftaran.id) AS total 
                FROM dc_pendaftaran
                INNER JOIN dc_pasien ON dc_pasien.id=dc_pendaftaran.id_pasien
                INNER JOIN dc_kelurahan ON dc_kelurahan.id=dc_pasien.id_kelurahan
                INNER JOIN dc_kecamatan ON dc_kecamatan.id=dc_kelurahan.id_kecamatan
                WHERE dc_kecamatan.id_kabupaten='".$kabupaten."' AND dc_pendaftaran.jenis='Poliklinik' AND dc_pendaftaran.jenis_igd IS NULL AND DATE(dc_pendaftaran.waktu_daftar) BETWEEN '".$tgl_awal."' AND '".$tgl_akhir."'
                GROUP BY dc_kelurahan.id, dc_pendaftaran.jenis
                UNION ALL
                SELECT dc_kecamatan.nama AS kecamatan, dc_kelurahan.nama AS kelurahan, dc_pendaftaran.jenis, dc_pendaftaran.jenis_igd, COUNT(dc_pendaftaran.id) AS total 
                FROM dc_pendaftaran
                INNER JOIN dc_pasien ON dc_pasien.id=dc_pendaftaran.id_pasien
                INNER JOIN dc_kelurahan ON dc_kelurahan.id=dc_pasien.id_kelurahan
                INNER JOIN dc_kecamatan ON dc_kecamatan.id=dc_kelurahan.id_kecamatan
                WHERE dc_kecamatan.id_kabupaten='".$kabupaten."' AND dc_pendaftaran.jenis='IGD' AND dc_pendaftaran.jenis_igd<>'Kamar Bersalin' AND DATE(dc_pendaftaran.waktu_daftar) BETWEEN '".$tgl_awal."' AND '".$tgl_akhir."'
                GROUP BY dc_kelurahan.id, dc_pendaftaran.jenis
                UNION ALL
                SELECT dc_kecamatan.nama AS kecamatan, dc_kelurahan.nama AS kelurahan, dc_pendaftaran.jenis, dc_pendaftaran.jenis_igd, COUNT(dc_pendaftaran.id) AS total 
                FROM dc_pendaftaran
                INNER JOIN dc_pasien ON dc_pasien.id=dc_pendaftaran.id_pasien
                INNER JOIN dc_kelurahan ON dc_kelurahan.id=dc_pasien.id_kelurahan
                INNER JOIN dc_kecamatan ON dc_kecamatan.id=dc_kelurahan.id_kecamatan
                WHERE dc_kecamatan.id_kabupaten='".$kabupaten."' AND dc_pendaftaran.jenis='IGD' AND dc_pendaftaran.jenis_igd='Kamar Bersalin' AND DATE(dc_pendaftaran.waktu_daftar) BETWEEN '".$tgl_awal."' AND '".$tgl_akhir."'
                GROUP BY dc_kelurahan.id, dc_pendaftaran.jenis
            ) AS q 
            ORDER BY kecamatan ASC, kelurahan ASC;
            ";
    
            $result = DB::select($sql_query);
            $data = array();
            $sub_area = array();
    
            $persentase_wilayah=array();
    
            $sub_rawat_jalan = $this->total_rawat_jalan($request);
    
            $sub_area = $this->fetch_data_kelurahan($result);
    
            foreach($this->fetch_data_kecamatan($result) as $row => $value){
    
                $total=0;
                if(sizeof($sub_area[$value]) > 0){
                    for($i=0;$i<sizeof($sub_area[$value]);$i++){
                        $total += $sub_area[$value][$i]['total'];
                    }
                } else{
                    $total = $sub_area[$value][0]['total'];
                }
    
                $label = number_format((($total/$sub_rawat_jalan["total"])*100), 2, '.', '')." %";
                $persentase_wilayah[] = array(
                    "value"=>$value, 
                    "label" => $label
                );
                
                $data[] = array(
                    "area" => $value,
                    "sub_area" => $sub_area[$value], 
                    "total" => $total
                );
            }

            $response = [
                "status" => "OK",
                "parameter" => $parameter,
                "persentase_wilayah" => $persentase_wilayah,
                "sub_rawat_jalan" => $sub_rawat_jalan["data"],
                "data" => $data
            ];

        } else {
            $response = [
                "status" => "OK",
                "parameter" => $parameter,
                "persentase_wilayah" => array(),
                "sub_rawat_jalan" => array(),
                "message" => "Data tidak ditemukan, input kategori dengan kelurahan.",
                "data" => array()
            ];
        }

        return $response;
    }

    public function total_rawat_jalan($request = array()){
        
        $tgl_awal = $request["tgl_awal"];
        $tgl_akhir = $request["tgl_akhir"];
        $kabupaten = $request["kabupaten"];

        $sql_query="SELECT * FROM (
                SELECT dc_pendaftaran.jenis, dc_pendaftaran.jenis_igd, COUNT(dc_pendaftaran.id) AS total 
                FROM dc_pendaftaran
                INNER JOIN dc_pasien ON dc_pasien.id=dc_pendaftaran.id_pasien
                INNER JOIN dc_kelurahan ON dc_kelurahan.id=dc_pasien.id_kelurahan
                INNER JOIN dc_kecamatan ON dc_kecamatan.id=dc_kelurahan.id_kecamatan
                WHERE dc_kecamatan.id_kabupaten='".$kabupaten."' AND dc_pendaftaran.jenis='Poliklinik' AND dc_pendaftaran.jenis_igd IS NULL AND DATE(dc_pendaftaran.waktu_daftar) BETWEEN '".$tgl_awal."' AND '".$tgl_akhir."'
                GROUP BY dc_pendaftaran.jenis
                UNION ALL
                SELECT dc_pendaftaran.jenis, dc_pendaftaran.jenis_igd, COUNT(dc_pendaftaran.id) AS total 
                FROM dc_pendaftaran
                INNER JOIN dc_pasien ON dc_pasien.id=dc_pendaftaran.id_pasien
                INNER JOIN dc_kelurahan ON dc_kelurahan.id=dc_pasien.id_kelurahan
                INNER JOIN dc_kecamatan ON dc_kecamatan.id=dc_kelurahan.id_kecamatan
                WHERE dc_kecamatan.id_kabupaten='".$kabupaten."' AND dc_pendaftaran.jenis='IGD' AND dc_pendaftaran.jenis_igd<>'Kamar Bersalin' AND DATE(dc_pendaftaran.waktu_daftar) BETWEEN '".$tgl_awal."' AND '".$tgl_akhir."'
                GROUP BY dc_pendaftaran.jenis
                UNION ALL
                SELECT dc_pendaftaran.jenis, dc_pendaftaran.jenis_igd, COUNT(dc_pendaftaran.id) AS total 
                FROM dc_pendaftaran
                INNER JOIN dc_pasien ON dc_pasien.id=dc_pendaftaran.id_pasien
                INNER JOIN dc_kelurahan ON dc_kelurahan.id=dc_pasien.id_kelurahan
                INNER JOIN dc_kecamatan ON dc_kecamatan.id=dc_kelurahan.id_kecamatan
                WHERE dc_kecamatan.id_kabupaten='".$kabupaten."' AND dc_pendaftaran.jenis='IGD' AND dc_pendaftaran.jenis_igd='Kamar Bersalin' AND DATE(dc_pendaftaran.waktu_daftar) BETWEEN '".$tgl_awal."' AND '".$tgl_akhir."'
                GROUP BY dc_pendaftaran.jenis
            ) AS q;
        ";

        $result = DB::select($sql_query);
        
        $data=array();
        $item="";
        $total=0;
        $total_all=0;
        foreach($result as $row){
            $jenis = $row->jenis;
            $jenis_igd = $row->jenis_igd;
            $total = $row->total;

            if($jenis=="Poliklinik" and $jenis_igd==""){
                $item="poliklinik";
            } else if($jenis == "IGD" and $jenis_igd!="Kamar Bersalin"){
                $item="igd";
            } else if($jenis == "IGD" and $jenis_igd=="Kamar Bersalin") {
                $item="kb";
            }

            $total_all += $total;

            $arr_item = array(
                "item" => $item,
                "total" => $total
            );

            array_push($data, $arr_item);
        }

        return array("data" => $data, "total" => $total_all);
    }

    public function fetch_data_kecamatan($result=array()){
        $data =array();
        $area = array();
        foreach($result as $row){
            $area[] = $row->kecamatan;
            $data = array_unique($area);
        }
        return $data;
    }

    public function fetch_data_kelurahan($result=array()){
        $sub_area = array();
        foreach($result as $row){
            $kec = $row->kecamatan;
            $kel = $row->kelurahan;
            $jenis = $row->jenis;
            $jenis_igd = $row->jenis_igd;

            $tot_poli=0;
            $tot_igd=0;
            $tot_kb=0;
            $total=0;

            if($jenis=="Poliklinik" and $jenis_igd==""){
                $tot_poli = $row->total;
            } else if($jenis == "IGD" and $jenis_igd!="Kamar Bersalin"){
                $tot_igd = $row->total;
            } else if($jenis == "IGD" and $jenis_igd=="Kamar Bersalin") {
                $tot_kb = $row->total;
            }

            $total=$tot_poli+$tot_igd+$tot_kb;

            $sub_area[$kec][] = array(
                "nama" => $kel ,
                "poliklinik" => $tot_poli,
                "igd" => $tot_igd,
                "kb" => $tot_kb,
                "total" => $total
            );

        }
        return $sub_area;
    }

    public function tgl_indo($tanggal){
        $bulan = array (
            1 => 'Januari',
            'Februari',
            'Maret',
            'April',
            'Mei',
            'Juni',
            'Juli',
            'Agustus',
            'September',
            'Oktober',
            'November',
            'Desember'
        );
        $pecahkan = explode('-', $tanggal);
        
        // variabel pecahkan 0 = tanggal
        // variabel pecahkan 1 = bulan
        // variabel pecahkan 2 = tahun
     
        return $pecahkan[2] . ' ' . $bulan[ (int)$pecahkan[1] ] . ' ' . $pecahkan[0];
    }
}
