-- The demo company: small and made-up, loaded on top of the pristine legacy install by the
-- parity suite's baseline and by `ledgerlift:demo-seed` (App\Database\DemoCompany). Every name,
-- address and email here is invented. Ids are fixed; invoice group 5 follows the seeded
-- groups 3 and 4. Settings stay at the install defaults.

INSERT INTO ip_tax_rates (tax_rate_id, tax_rate_name, tax_rate_percent) VALUES
    (1, 'VAT', 15.00),
    (2, 'Reduced', 5.00),
    (3, 'Service', 7.50);

INSERT INTO ip_families (family_id, family_name) VALUES
    (1, 'Services'),
    (2, 'Hosting');

INSERT INTO ip_units (unit_id, unit_name, unit_name_plrl) VALUES
    (1, 'hour', 'hours'),
    (2, 'month', 'months');

INSERT INTO ip_products (product_id, family_id, product_sku, product_name, product_description, product_price, purchase_price, provider_name, tax_rate_id, unit_id) VALUES
    (1, 1, 'SRV-DEV', 'Development', 'Software development, billed per hour', 95.00, 60.00, 'In-house', 1, 1),
    (2, 1, 'SRV-CON', 'Consulting', 'Technical consulting, billed per hour', 120.00, 70.00, 'In-house', 3, 1),
    (3, 1, 'SRV-SUP', 'Support', 'Help-desk support, billed per hour', 65.00, 40.00, 'In-house', 2, 1),
    (4, 2, 'HST-BAS', 'Basic hosting', 'Shared hosting plan, billed monthly', 19.90, 8.00, 'Quillstone Datacentre', 1, 2),
    (5, 2, 'HST-MGD', 'Managed hosting', 'Managed server with monitoring, billed monthly', 79.00, 35.00, 'Quillstone Datacentre', 1, 2),
    (6, 2, 'HST-BKP', 'Offsite backup', 'Nightly offsite backup, billed monthly', 9.50, 3.25, 'Quillstone Datacentre', 2, 2);

INSERT INTO ip_clients (client_id, client_date_created, client_date_modified, client_name, client_address_1, client_city, client_zip, client_country, client_phone, client_email, client_active) VALUES
    (1, '2026-01-05 09:00:00', '2026-01-05 09:00:00', 'Ashgrove Joinery', '12 Mill Lane', 'Eastbury', '10001', 'US', '555-0101', 'accounts@ashgrove-joinery.test', 1),
    (2, '2026-01-05 09:00:00', '2026-01-05 09:00:00', 'Bellmont Lighting Co', '48 Harbour Road', 'Westfield', '10002', 'US', '555-0102', 'billing@bellmont-lighting.test', 1),
    (3, '2026-01-05 09:00:00', '2026-01-05 09:00:00', 'Cinderpeak Robotics', '7 Foundry Street', 'Northvale', '10003', 'US', '555-0103', 'finance@cinderpeak-robotics.test', 1),
    (4, '2026-01-05 09:00:00', '2026-01-05 09:00:00', 'Driftwood Bakery', '3 Quay Parade', 'Southport', '10004', 'US', '555-0104', 'hello@driftwood-bakery.test', 1),
    (5, '2026-01-05 09:00:00', '2026-01-05 09:00:00', 'Emberline Studio', '90 Canal Walk', 'Eastbury', '10005', 'US', '555-0105', 'studio@emberline.test', 1),
    (6, '2026-01-05 09:00:00', '2026-01-05 09:00:00', 'Fernhill Logistics', '21 Depot Way', 'Westfield', '10006', 'US', '555-0106', 'ap@fernhill-logistics.test', 1),
    (7, '2026-01-05 09:00:00', '2026-01-05 09:00:00', 'Glasswing Optics', '5 Lens Court', 'Northvale', '10007', 'US', '555-0107', 'office@glasswing-optics.test', 0),
    (8, '2026-01-05 09:00:00', '2026-01-05 09:00:00', 'Hollowbrook Farms', '1 Orchard Row', 'Southport', '10008', 'US', '555-0108', 'farm@hollowbrook.test', 0);

INSERT INTO ip_payment_methods (payment_method_id, payment_method_name) VALUES
    (3, 'Bank transfer');

INSERT INTO ip_invoice_groups (invoice_group_id, invoice_group_name, invoice_group_identifier_format, invoice_group_next_id, invoice_group_left_pad) VALUES
    (5, 'Invoice INV', 'INV-{{{year}}}-{{{id}}}', 1, 4);
