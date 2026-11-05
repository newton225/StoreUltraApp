import fs from 'fs';

const BS = '\\';
const ed = (f, a, b) => {
    const s = fs.readFileSync(f, 'utf8');
    if (!s.includes(a)) throw new Error(f + ' missing ' + a.slice(0, 60));
    fs.writeFileSync(f, s.replace(a, () => b));
};

// 1. import of the Audited trait in the models
for (const m of ['Client', 'ClientMember', 'Zone', 'Product', 'ProductPrice', 'Kit', 'Shop', 'PaymentMethod', 'ExpenseType', 'AttachmentType', 'Setting']) {
    const f = `app/Models/${m}.php`;
    const s = fs.readFileSync(f, 'utf8');
    if (s.includes(`use App${BS}Models${BS}Concerns${BS}Audited;`)) continue;
    fs.writeFileSync(f, s.replace(/^namespace App.Models;\r?\n/m, (x) => `${x}\nuse App${BS}Models${BS}Concerns${BS}Audited;`));
}

// 2. traces of the group actions and of the import
let f = 'app/Actions/Orders/IssueProformasForLocality.php';
ed(f, '                    $this->transition->handle($order, OrderStatus::ProformaIssued, null, $user);', "                    $this->transition->handle($order, OrderStatus::ProformaIssued, null, $user);\n\n                    AuditLog::record('order.batch_step', $order, [], ['step' => 'proforma_issued', 'locality' => $locality, 'group_of' => $total], $user);");
ed(f, '        foreach ($this->pending($locality, $user)->orderBy(\'id\')->get() as $order) {', "        $orders = $this->pending($locality, $user)->orderBy('id')->get();\n        $total = $orders->count();\n\n        foreach ($orders as $order) {");
ed(f, `use App${BS}Models${BS}Document;`, `use App${BS}Models${BS}AuditLog;\nuse App${BS}Models${BS}Document;`);
ed(f, 'function () use ($order, $locale, $user): void {', 'function () use ($order, $locale, $user, $locality, $total): void {');

f = 'app/Actions/Orders/ReceiveAdvancesForLocality.php';
ed(f, '                $this->transition->handle($order, OrderStatus::AdvanceReceived, null, $user);', "                $this->transition->handle($order, OrderStatus::AdvanceReceived, null, $user);\n                AuditLog::record('order.batch_step', $order, [], ['step' => 'advance_received', 'locality' => $locality, 'paid' => $order->netPaid(), 'due' => $order->advance_due, 'group_of' => $picked->count()], $user);");
ed(f, '        foreach ($this->settled($locality, $user)->whereIn(\'id\', $orderIds) as $order) {', "        $picked = $this->settled($locality, $user)->whereIn('id', $orderIds);\n\n        foreach ($picked as $order) {");
ed(f, `use App${BS}Models${BS}Order;`, `use App${BS}Models${BS}AuditLog;\nuse App${BS}Models${BS}Order;`);

f = 'app/Actions/Clients/ImportTpeClients.php';
ed(f, '                foreach ($tpe[\'people\'] as $position => $person) {', "                AuditLog::record('client.imported', $client, [], ['source' => basename($path), 'sheet' => $tpe['sheet'], 'team' => count($tpe['people'])]);\n\n                foreach ($tpe['people'] as $position => $person) {");
ed(f, `use App${BS}Models${BS}Client;`, `use App${BS}Models${BS}AuditLog;\nuse App${BS}Models${BS}Client;`);
