<?php

namespace App\Http\Controllers;

class ExportCsvController extends Controller
{

    public function export($data)
    {
        // $data = [
        //     ['Nome', 'Email', 'Idade'],
        //     ['João', 'joao@example.com', 30],
        //     ['Maria', 'maria@example.com', 25],
        //     ['Pedro', 'pedro@example.com', 35],
        // ];

        $handle = fopen('php://output', 'w');

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="dados.csv"');

        foreach ($data as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
        exit;
    }


}
