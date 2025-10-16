<?php
	/**
	* The admins class
	* It contains all action and behaviours admins may have
	*/
	class Admins
	{

		private $dbh = null;

		public function __construct($db)
		{
			$this->dbh = $db;
		}

		public function loginAdmin($user_name, $user_pwd)
		{
			//Un-comment this to see a cryptogram of a user_pwd 
			// echo session::hashuser_pwd($user_pwd);
			// die;
			$request = $this->dbh->prepare("SELECT user_id, user_name, user_pwd, role, location FROM kp_user WHERE user_name = ?");
	        if($request->execute( array($user_name) ))
	        {
	        	// This is an array of objects.
	        	// Remember we setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ); in config/dbconnection.php
	        	$data = $request->fetchAll();
	        	
	        	// But if things are right, the array should contain only one object, the corresponding user
	        	// so, we can do this
			if (count($data) > 0) {
					$data = $data[0];

					if (session::passwordMatch($user_pwd, $data->user_pwd)) {
						return $data;
					}
				}

			return false;

	        }else{
	        	return false;
	        }

		}

		/**
		 * Check if the admin user_name is unique
		 * If though we've set this criteria in our database,
		 * It's good to make sure the user is not try that
		 * @param   $user_name The user_name
		 * @return Boolean If the user_name is already usedor not
		 * 
		 */
		public function adminExists( $user_name )
		{
			$request = $this->dbh->prepare("SELECT user_name FROM kp_user WHERE user_name = ?");
			$request->execute([$user_name]);
			$Admindata = $request->fetchAll();
			return sizeof($Admindata) != 0;
		}

		/**
		 * Compare two user_pwds
		 * @param String $user_pwd1, $user_pwd2 The two user_pwds
		 * @return  Boolean Either true or false
		 */

		public function ArepasswordSame( $user_pwd1, $user_pwd2 )
		{
			return strcmp( $user_pwd1, $user_pwd2 ) == 0;
		}


	/**
	 * ADMIN RELATED FUNCTIONS ###################################################################################################################
	 */
		
		/**
		 * Create a new row of admin
		 * @param String $user_name New admin user_name
		 * @param String $user_pwd New Admin user_pwd
		 * @return Boolean The final state of the action
		 * 
		 */
		
		public function addNewAdmin($user_name, $user_pwd, $email, $full_name, $address, $contact, $role = 'admin', $location = null, $profile_pic = null)
		{
			$request = $this->dbh->prepare("INSERT INTO kp_user (user_name, user_pwd, email, full_name, address, contact, role, location, profile_pic) VALUES(?,?,?,?,?,?,?,?,?) ");

			// Do not forget to encrypt the pasword before saving
			return $request->execute([$user_name, session::hashPassword($user_pwd), $email, $full_name, $address, $contact, $role, $location, $profile_pic]);
		}
		/**
		 * Fetch admins
		 */
		
		public function fetchAdmin($limit = 10)
		{
			$limit = (int) $limit;
			$request = $this->dbh->prepare("SELECT * FROM kp_user  ORDER BY user_id DESC  LIMIT :limit");
			$request->bindValue(':limit', $limit, PDO::PARAM_INT);
			if ($request->execute()) {
				return $request->fetchAll();
			}
			return false;
		}

		/**
		 * Fetch admins paginated with optional search query.
		 */
		public function fetchAdminPage($offset = 0, $limit = 10, $query = null)
		{
			$offset = max(0, (int)$offset);
			$limit = max(1, (int)$limit);
			$sql = "SELECT * FROM kp_user WHERE 1=1";
			$params = [];
			if ($query !== null && $query !== '') {
				$sql .= " AND (user_name LIKE ? OR full_name LIKE ? OR email LIKE ? OR contact LIKE ? OR address LIKE ?)";
				$like = "%" . $query . "%";
				$params = [$like, $like, $like, $like, $like];
			}
			$sql .= " ORDER BY user_id DESC LIMIT $offset, $limit";
			$request = $this->dbh->prepare($sql);
			if ($request->execute($params)) {
				return $request->fetchAll();
			}
			return false;
		}

		public function countAdmin($query = null)
		{
			$sql = "SELECT COUNT(*) as total FROM kp_user WHERE 1=1";
			$params = [];
			if ($query !== null && $query !== '') {
				$sql .= " AND (user_name LIKE ? OR full_name LIKE ? OR email LIKE ? OR contact LIKE ? OR address LIKE ?)";
				$like = "%" . $query . "%";
				$params = [$like, $like, $like, $like, $like];
			}
			$request = $this->dbh->prepare($sql);
			if ($request->execute($params)) {
				$row = $request->fetch();
				return $row ? (int)$row->total : 0;
			}
			return 0;
		}

		/* duplicate removed */

		public function getEmployerMonitoringData()
		{
			$current_month = date('Y-m');
			$request = $this->dbh->prepare("
				SELECT
					u.user_id,
					u.full_name,
					u.location,
					u.profile_pic,
					COUNT(DISTINCT c.id) AS total_customers,
					COUNT(DISTINCT CASE WHEN p.status = 'Paid' AND DATE_FORMAT(p.p_date, '%Y-%m') = :current_month THEN c.id END) AS paid_customers,
					COUNT(DISTINCT CASE WHEN c.id IS NOT NULL AND (p.status != 'Paid' OR p.status IS NULL) THEN c.id END) AS unpaid_customers,
					COALESCE(SUM(CASE WHEN p.status = 'Paid' AND DATE_FORMAT(p.p_date, '%Y-%m') = :current_month THEN p.amount ELSE 0 END), 0) AS monthly_paid_collection,
					COALESCE(SUM(CASE WHEN p.status = 'Unpaid' AND DATE_FORMAT(p.g_date, '%Y-%m') = :current_month THEN p.amount ELSE 0 END), 0) AS monthly_unpaid_collection,
					COALESCE(SUM(p.balance), 0) AS total_balance
				FROM
					kp_user u
				LEFT JOIN
					customers c ON u.user_id = c.employer_id
				LEFT JOIN
					payments p ON c.id = p.customer_id
				WHERE
					u.role = 'employer'
				GROUP BY
					u.user_id, u.full_name, u.location
				ORDER BY
					u.full_name
			");

			$request->execute(['current_month' => $current_month]);
			$results = $request->fetchAll();

			$data = [];
			foreach ($results as $row) {
				$employer_data = [
					'info' => (object)[
						'user_id' => $row->user_id,
						'full_name' => $row->full_name,
						'location' => $row->location,
						'profile_pic' => $row->profile_pic,
					],
					'stats' => [
						'total_customers' => (int)$row->total_customers,
						'paid_customers' => (int)$row->paid_customers,
						'unpaid_customers' => (int)$row->unpaid_customers,
						'monthly_paid_collection' => (float)$row->monthly_paid_collection,
						'monthly_unpaid_collection' => (float)$row->monthly_unpaid_collection,
						'total_balance' => (float)$row->total_balance,
					],
				];
				$data[] = (object)$employer_data;
			}

			return $data;
		}

		public function getEmployerNameById($id)
		{
			$request = $this->dbh->prepare("SELECT full_name FROM kp_user WHERE user_id = ?");
			if ($request->execute([$id])) {
				$result = $request->fetch();
				return $result ? $result->full_name : null;
			}
			return null;
		}

        public function fetchCustomerStatusByEmployer($employer_id)
        {
            // Fixed status logic: Unpaid (new client), Paid (full payment), Balance (initial payment)
            $request = $this->dbh->prepare("
                SELECT
                    status,
                    COUNT(*) as count
                FROM (
                    SELECT
                        c.id,
                        CASE
                            WHEN EXISTS (SELECT 1 FROM payments px WHERE px.customer_id = c.id AND px.status = 'Pending') THEN 'Pending'
                            WHEN EXISTS (SELECT 1 FROM payments rx WHERE rx.customer_id = c.id AND rx.status = 'Rejected') THEN 'Rejected'
                            WHEN c.dropped = 1 THEN 'Unpaid'
                            WHEN COALESCE(p.total_balance, 0) > 0 AND COALESCE(p.total_paid, 0) > 0 THEN 'Balance'
                            WHEN COALESCE(p.total_balance, 0) > 0 AND COALESCE(p.total_paid, 0) = 0 THEN 'Unpaid'
                            WHEN COALESCE(p.total_paid, 0) > 0 AND COALESCE(p.total_balance, 0) = 0 THEN 'Paid'
                            WHEN p.total_paid IS NULL AND p.total_balance IS NULL THEN 'Unpaid'
                            ELSE 'Unpaid'
                        END as status
                    FROM
                        customers c
                    LEFT JOIN (
                        SELECT customer_id, SUM(amount - balance) AS total_paid, SUM(balance) AS total_balance
                        FROM payments
                        GROUP BY customer_id
                    ) p ON p.customer_id = c.id
                    WHERE
                        c.employer_id = ?
                ) as customer_status
                GROUP BY
                    status
            ");
            if ($request->execute([$employer_id])) {
                return $request->fetchAll();
            }
            return false;
        }

		public function fetchProductsByEmployer($employer_id)
		{
			$request = $this->dbh->prepare("SELECT p.*, COUNT(c.id) as customer_count FROM packages p JOIN customers c ON p.id = c.package_id WHERE c.employer_id = ? GROUP BY p.id");
			if ($request->execute([$employer_id])) {
				return $request->fetchAll();
			}
			return false;
		}

	public function fetchCustomersByEmployer($employer_id, $limit = 10)
	{
		$limit = (int) $limit;
		$request = $this->dbh->prepare("
			SELECT
				c.*,
				COALESCE(p.total_paid, 0) as total_paid,
				COALESCE(p.total_balance, 0) as total_balance,
				CASE
					WHEN EXISTS (SELECT 1 FROM payments WHERE customer_id = c.id AND status = 'Pending') THEN 'Pending'
					WHEN EXISTS (SELECT 1 FROM payments WHERE customer_id = c.id AND status = 'Rejected') THEN 'Rejected'
					WHEN c.dropped = 1 THEN 'Unpaid'
					WHEN COALESCE(p.total_balance, 0) > 0 AND COALESCE(p.total_paid, 0) > 0 THEN 'Balance'
					WHEN COALESCE(p.total_balance, 0) > 0 AND COALESCE(p.total_paid, 0) = 0 THEN 'Unpaid'
					WHEN COALESCE(p.total_paid, 0) > 0 AND COALESCE(p.total_balance, 0) = 0 THEN 'Paid'
					WHEN p.total_paid IS NULL AND p.total_balance IS NULL THEN 'Unpaid'
					ELSE 'Unpaid'
				END AS status
			FROM
				customers c
			LEFT JOIN
				(SELECT
					customer_id,
					SUM(amount - balance) as total_paid,
					SUM(balance) as total_balance
				FROM
					payments
				GROUP BY
					customer_id
				) p ON c.id = p.customer_id
			WHERE
				c.employer_id = ?
			ORDER BY
				c.id DESC
			LIMIT ?
		");
		$request->bindValue(1, $employer_id, PDO::PARAM_INT);
		$request->bindValue(2, $limit, PDO::PARAM_INT);
		if ($request->execute()) {
			return $request->fetchAll();
		}
		return false;
	}

	/**
	 * Fetch customers by employer with pagination support
	 */
	public function fetchCustomersByEmployerPage($employer_id, $offset = 0, $limit = 10)
	{
		$offset = max(0, (int)$offset);
		$limit = max(1, (int)$limit);
		$request = $this->dbh->prepare("
			SELECT
				c.*,
				COALESCE(p.total_paid, 0) as total_paid,
				COALESCE(p.total_balance, 0) as total_balance,
				CASE
					WHEN EXISTS (SELECT 1 FROM payments WHERE customer_id = c.id AND status = 'Pending') THEN 'Pending'
					WHEN EXISTS (SELECT 1 FROM payments WHERE customer_id = c.id AND status = 'Rejected') THEN 'Rejected'
					WHEN c.dropped = 1 THEN 'Unpaid'
					WHEN COALESCE(p.total_balance, 0) > 0 AND COALESCE(p.total_paid, 0) > 0 THEN 'Balance'
					WHEN COALESCE(p.total_balance, 0) > 0 AND COALESCE(p.total_paid, 0) = 0 THEN 'Unpaid'
					WHEN COALESCE(p.total_paid, 0) > 0 AND COALESCE(p.total_balance, 0) = 0 THEN 'Paid'
					WHEN p.total_paid IS NULL AND p.total_balance IS NULL THEN 'Unpaid'
					ELSE 'Unpaid'
				END AS status
			FROM
				customers c
			LEFT JOIN
				(SELECT
					customer_id,
					SUM(amount - balance) as total_paid,
					SUM(balance) as total_balance
				FROM
					payments
				GROUP BY
					customer_id
				) p ON c.id = p.customer_id
			WHERE
				c.employer_id = ?
			ORDER BY
				c.id DESC
			LIMIT $offset, $limit
		");
		if ($request->execute([$employer_id])) {
			return $request->fetchAll();
		}
		return false;
	}

	/**
	 * Count total customers for an employer
	 */
	public function countCustomersByEmployer($employer_id)
	{
		$request = $this->dbh->prepare("SELECT COUNT(*) as total FROM customers WHERE employer_id = ?");
		if ($request->execute([$employer_id])) {
			$row = $request->fetch();
			return $row ? (int)$row->total : 0;
		}
		return 0;
	}

		public function fetchAllIndividualBill($customer_id, $status = null)
		{
			$sql = "SELECT *, (`amount` - `balance`) as paid FROM `payments` WHERE customer_id = ?";
			$params = [$customer_id];
			if ($status !== null) {
				$sql .= " AND status = ?";
				$params[] = $status;
			}
			$request = $this->dbh->prepare($sql);
			if ($request->execute($params)) {
				return $request->fetchAll();
			}
			return false;
		}

		/**
		 * Get customer status based on payment history
		 * Returns: 'Unpaid', 'Balance', or 'Paid'
		 */
		public function getCustomerStatus($customer_id)
		{
			$request = $this->dbh->prepare("
				SELECT
					COALESCE(SUM(amount - balance), 0) as total_paid,
					COALESCE(SUM(balance), 0) as total_balance
				FROM payments
				WHERE customer_id = ?
			");
			if ($request->execute([$customer_id])) {
				$result = $request->fetch();
				$total_paid = (float)$result->total_paid;
				$total_balance = (float)$result->total_balance;

				if ($total_paid == 0 && $total_balance > 0) {
					return 'Unpaid';
				} elseif ($total_paid > 0 && $total_balance > 0) {
					return 'Balance';
				} elseif ($total_paid > 0 && $total_balance == 0) {
					return 'Paid';
				}
			}
			return 'Unpaid';
		}

		public function getEmployerById($id)
		{
			$request = $this->dbh->prepare("SELECT * FROM kp_user WHERE user_id = ?");
			if ($request->execute([$id])) {
				return $request->fetch();
			}
			return false;
		}

		public function getEmployers()
		{
			$request = $this->dbh->prepare("SELECT * FROM kp_user WHERE role = 'employer' ORDER BY user_id DESC");
			if ($request->execute()) {
				return $request->fetchAll();
			}
			return false;
		}

		public function fetchCustomerDetails($customerId)
		{
			$details = [
				'info' => null,
				'bills' => [],
				'transactions' => [],
			];

			// Fetch customer info
			$request = $this->dbh->prepare("SELECT * FROM customers WHERE id = ?");
			if ($request->execute([$customerId])) {
				$details['info'] = $request->fetch();
			}

			// Fetch all bills
			$request = $this->dbh->prepare("SELECT * FROM payments WHERE customer_id = ?");
			if ($request->execute([$customerId])) {
				$details['bills'] = $request->fetchAll();
			}

			// Fetch transactions (legacy summary slips) and detailed ledger
			$request = $this->dbh->prepare("SELECT * FROM billings WHERE customer_id = ?");
			if ($request->execute([$customerId])) {
				$details['transactions'] = $request->fetchAll();
			}

			return $details;
		}

		public function getEmployerByLocation($location)
		{
			$request = $this->dbh->prepare("SELECT * FROM kp_user WHERE role = 'admin' AND location = ?");
			if ($request->execute([$location])) {
				return $request->fetch();
			}
			return false;
		}

        public function fetchCustomerStatusByLocation($location)
        {
            // Fixed status logic: Unpaid (new client), Paid (full payment), Balance (initial payment)
            $request = $this->dbh->prepare("
                SELECT
                    status,
                    COUNT(*) as count
                FROM (
                    SELECT
                        c.id,
                        CASE
                            WHEN EXISTS (SELECT 1 FROM payments px WHERE px.customer_id = c.id AND px.status = 'Pending') THEN 'Pending'
                            WHEN EXISTS (SELECT 1 FROM payments rx WHERE rx.customer_id = c.id AND rx.status = 'Rejected') THEN 'Rejected'
                            WHEN c.dropped = 1 THEN 'Unpaid'
                            WHEN COALESCE(p.total_balance, 0) > 0 AND COALESCE(p.total_paid, 0) > 0 THEN 'Balance'
                            WHEN COALESCE(p.total_balance, 0) > 0 AND COALESCE(p.total_paid, 0) = 0 THEN 'Unpaid'
                            WHEN COALESCE(p.total_paid, 0) > 0 AND COALESCE(p.total_balance, 0) = 0 THEN 'Paid'
                            WHEN p.total_paid IS NULL AND p.total_balance IS NULL THEN 'Unpaid'
                            ELSE 'Unpaid'
                        END as status
                    FROM
                        customers c
                    LEFT JOIN (
                        SELECT customer_id, SUM(amount - balance) AS total_paid, SUM(balance) AS total_balance
                        FROM payments
                        GROUP BY customer_id
                    ) p ON p.customer_id = c.id
                    WHERE
                        ? LIKE CONCAT('%', c.conn_location, '%')
                ) as customer_status
                GROUP BY
                    status
            ");
            if ($request->execute([$location])) {
                return $request->fetchAll();
            }
            return false;
        }

		public function fetchCustomerCountByLocation()
		{
			$request = $this->dbh->prepare("SELECT conn_location, COUNT(*) as count FROM customers WHERE dropped = 0 GROUP BY conn_location");
			if ($request->execute()) {
				return $request->fetchAll();
			}
			return false;
		}

		public function fetchCustomerByLoginCode($login_code)
		{
			$request = $this->dbh->prepare("SELECT * FROM customers WHERE login_code = ?");
			if ($request->execute([$login_code])) {
				return $request->fetch();
			}
			return false;
		}
		/**
		 * Update Admin
		 */
		public function updateAdmin($id, $user_name, $email, $full_name, $address, $contact)
		{
			$request = $this->dbh->prepare("UPDATE kp_user SET user_name =?, email =?, full_name =?, address= ?, contact =? WHERE user_id =?");
			return $request->execute([$user_name, $email, $full_name, $address, $contact, $id]);
		}



	
		/**
		 * Delete an user
		 */
		public function deleteUser($id)
		{
			$request = $this->dbh->prepare("DELETE FROM kp_user WHERE user_id = ?");
			return $request->execute([$id]);
		}



		/**
	 * Customers RELATED FUNCTIONS ###################################################################################################################
	 */
		
		/**
		 * Create a new row of Customers
		 * 
		 */
		
		public function addCustomer($full_name, $nid, $address, $conn_location, $email, $package, $ip_address, $conn_type, $contact, $login_code, $employer_id)
		{
			$request = $this->dbh->prepare("INSERT INTO customers (`full_name`, `nid`, `address`, `conn_location`, `email`, `package_id`, `ip_address`, `conn_type`, `contact`, `login_code`, `employer_id`) VALUES(?,?,?,?,?,?,?,?,?,?,?)");
			// Do not forget to encrypt the pasword before saving
			if ($request->execute([$full_name, $nid, $address, $conn_location, $email, $package, $ip_address, $conn_type, $contact, $login_code, $employer_id])) {
				return $this->dbh->lastInsertId();
			}
			return false;
		}
		/**
		 * Fetch Customers
		 */
		
		public function fetchCustomer($limit = 10)
		{
			$limit = (int) $limit;
			$request = $this->dbh->prepare("
                SELECT
                    c.*,
                    u.full_name as employer_name,
                    COALESCE(p.total_paid, 0) as total_paid,
                    COALESCE(p.total_balance, 0) as total_balance
                FROM
                    customers c
                LEFT JOIN
                    kp_user u ON c.employer_id = u.user_id
                LEFT JOIN
                    (SELECT
                        customer_id,
                        SUM(amount - balance) as total_paid,
                        SUM(balance) as total_balance
                    FROM
                        payments
                    GROUP BY
                        customer_id
                    ) p ON c.id = p.customer_id
                ORDER BY
                    c.id DESC
                LIMIT :limit");
			$request->bindValue(':limit', $limit, PDO::PARAM_INT);
			if ($request->execute()) {
				return $request->fetchAll();
			}
			return false;
		}
		/**
		 * Update Customers
		 */
		public function updateCustomer($id, $full_name, $nid, $address, $conn_location, $email, $package, $ip_address, $conn_type, $contact, $employer_id)
		{
			$request = $this->dbh->prepare("UPDATE customers SET full_name =?, nid =?, address =?, conn_location= ?, email =?, package_id =?, ip_address=?, conn_type=?, contact=?, employer_id = ? WHERE id =?");
			return $request->execute([$full_name, $nid, $address, $conn_location, $email, $package, $ip_address, $conn_type, $contact, $employer_id, $id]);
		}



	
		/**
		 * Delete a Customer
		 */
		public function deleteCustomer($id)
		{
			try {
				$this->dbh->beginTransaction();

				$request = $this->dbh->prepare("DELETE FROM payments WHERE customer_id = ?");
				$request->execute([$id]);

				$request = $this->dbh->prepare("DELETE FROM billings WHERE customer_id = ?");
				$request->execute([$id]);

				$request = $this->dbh->prepare("DELETE FROM customers WHERE id = ?");
				$request->execute([$id]);

				$this->dbh->commit();
				return true;
			} catch (Exception $e) {
				$this->dbh->rollBack();
				return false;
			}
		}



		public function fetchCustomersByLocation($location, $limit = 10)
		{
			$limit = (int) $limit;
			$request = $this->dbh->prepare("SELECT * FROM customers WHERE ? LIKE CONCAT('%', conn_location, '%') ORDER BY id DESC LIMIT ?");
			$request->bindValue(1, $location);
			$request->bindValue(2, $limit, PDO::PARAM_INT);
			if ($request->execute()) {
				return $request->fetchAll();
			}
			return false;
		}

		/**
		 * Fetch customers paginated with optional search query (global admin list).
		 */
		public function fetchCustomersPage($offset = 0, $limit = 10, $query = null)
		{
			$offset = max(0, (int)$offset);
			$limit = max(1, (int)$limit);
			$params = [];
			$sql = "\n                SELECT\n                    c.*,\n                    u.full_name as employer_name,\n                    COALESCE(p.total_paid, 0) as total_paid,\n                    COALESCE(p.total_balance, 0) as total_balance\n                FROM customers c\n                LEFT JOIN kp_user u ON c.employer_id = u.user_id\n                LEFT JOIN (\n                    SELECT customer_id, SUM(amount - balance) as total_paid, SUM(balance) as total_balance\n                    FROM payments GROUP BY customer_id\n                ) p ON c.id = p.customer_id\n                WHERE 1=1";
			if ($query !== null && $query !== '') {
				$sql .= " AND (c.full_name LIKE ? OR c.nid LIKE ? OR c.address LIKE ? OR c.email LIKE ? OR c.ip_address LIKE ? OR c.conn_type LIKE ? OR c.contact LIKE ? OR c.login_code LIKE ? OR u.full_name LIKE ?)";
				$like = "%" . $query . "%";
				$params = [$like,$like,$like,$like,$like,$like,$like,$like,$like];
			}
			$sql .= " ORDER BY c.id DESC LIMIT $offset, $limit";
			$request = $this->dbh->prepare($sql);
			if ($request->execute($params)) {
				return $request->fetchAll();
			}
			return false;
		}

		public function countCustomers($query = null)
		{
			$params = [];
			$sql = "SELECT COUNT(*) as total FROM customers c LEFT JOIN kp_user u ON c.employer_id = u.user_id WHERE 1=1";
			if ($query !== null && $query !== '') {
				$sql .= " AND (c.full_name LIKE ? OR c.nid LIKE ? OR c.address LIKE ? OR c.email LIKE ? OR c.ip_address LIKE ? OR c.conn_type LIKE ? OR c.contact LIKE ? OR c.login_code LIKE ? OR u.full_name LIKE ?)";
				$like = "%" . $query . "%";
				$params = [$like,$like,$like,$like,$like,$like,$like,$like,$like];
			}
			$request = $this->dbh->prepare($sql);
			if ($request->execute($params)) {
				$row = $request->fetch();
				return $row ? (int)$row->total : 0;
			}
			return 0;
		}

		public function fetchProductsByCustomerLocation($location)
		{
			$request = $this->dbh->prepare("SELECT p.*, COUNT(c.id) as customer_count FROM packages p JOIN customers c ON p.id = c.package_id WHERE ? LIKE CONCAT('%', c.conn_location, '%') GROUP BY p.id");
			if ($request->execute([$location])) {
				return $request->fetchAll();
			}
			return false;
		}




	/**
	 * Product RELATED FUNCTIONS ###################################################################################################################
	 */
		/**
		 * Create a new row of product
		 * 
		 */
		public function addNewProduct($name, $unit, $details, $category)
		{
			try {
					$request = $this->dbh->prepare("INSERT INTO kp_products (pro_name, pro_unit, pro_details, pro_category) VALUES(?,?,?,?) ");
					return $request->execute([$name, $unit, $details, $category]);
			} catch (Exception $e) {
				return false;
			}
		}


		/**
		 * Check if a  product exists with the same name
		 */
		public function productExists( $pro_name )
		{
			$request = $this->dbh->prepare("SELECT pro_name FROM kp_products WHERE pro_name = ?");
			$request->execute([$pro_name]);
			$Admindata = $request->fetchAll();
			return sizeof($Admindata) != 0;
		}

		/**
		 * Update product
		 */
		public function updateProduct($id, $name, $unit, $details, $category)
		{
			$request = $this->dbh->prepare("UPDATE kp_products SET pro_name = ?, pro_unit = ?, pro_details = ?, pro_category = ? WHERE pro_id = ? ");
			return $request->execute([$name, $unit, $details, $category, $id]);
		}



		/**
		 * Delete a product with id
		 */
		public function deleteProduct($id)
		{
			$request = $this->dbh->prepare("DELETE FROM kp_products WHERE pro_id = ?");
			return $request->execute([$id]);
		}

		/**
		 * Fetch category
		 */
		
		public function fetchCategory()
		{
			$request = $this->dbh->prepare("SELECT cat_name FROM kp_category  ORDER BY cat_id ");
			if ($request->execute()) {
				return $request->fetchAll();
			}
			return false;
		}


		/**
		 * Fetch products all limit of 100
		 */
		
		public function fetchProducts($limit = 100)
		{
			$limit = (int) $limit;
			$request = $this->dbh->prepare("SELECT * FROM kp_products ORDER BY pro_id  LIMIT :limit");
			$request->bindValue(':limit', $limit, PDO::PARAM_INT);
			if ($request->execute()) {
				return $request->fetchAll();
			}
			return false;
		}

		/**
		 * Fetch products with pagination and optional free-text search.
		 */
		public function fetchProductsPage($offset = 0, $limit = 10, $query = null)
		{
			$offset = max(0, (int)$offset);
			$limit = max(1, (int)$limit);
			$params = [];
			$sql = "SELECT * FROM kp_products WHERE 1=1";
			if ($query !== null && $query !== '') {
				$sql .= " AND (pro_name LIKE ? OR pro_unit LIKE ? OR pro_category LIKE ? OR pro_details LIKE ?)";
				$like = "%" . $query . "%";
				$params = [$like,$like,$like,$like];
			}
			$sql .= " ORDER BY pro_id DESC LIMIT $offset, $limit";
			$request = $this->dbh->prepare($sql);
			if ($request->execute($params)) {
				return $request->fetchAll();
			}
			return false;
		}

		public function countProducts($query = null)
		{
			$params = [];
			$sql = "SELECT COUNT(*) as total FROM kp_products WHERE 1=1";
			if ($query !== null && $query !== '') {
				$sql .= " AND (pro_name LIKE ? OR pro_unit LIKE ? OR pro_category LIKE ? OR pro_details LIKE ?)";
				$like = "%" . $query . "%";
				$params = [$like,$like,$like,$like];
			}
			$request = $this->dbh->prepare($sql);
			if ($request->execute($params)) {
				$row = $request->fetch();
				return $row ? (int)$row->total : 0;
			}
			return 0;
		}

		/**
		 *	Fetch a Single product
		 */

		public function getAProduct($id)
		{
			$request = $this->dbh->prepare("SELECT * FROM kp_products WHERE pro_id = ?");
			if ($request->execute([$id])) {
				return $request->fetch();
			}
			return false;
		}


		

		/**
		*Fetch production from database
		*/
		public function fetchProduction($limit = 5)
		{
			$limit = (int) $limit;
			$request = $this->dbh->prepare("SELECT * FROM product WHERE type=1 ORDER BY id DESC LIMIT :limit");
			$request->bindValue(':limit', $limit, PDO::PARAM_INT);
			if ($request->execute()) {
				return $request->fetchAll();
			}
			return false;
		}
		public function fetchProductionSend($limit = 5)
		{
			$limit = (int) $limit;
			$request = $this->dbh->prepare("SELECT * FROM product WHERE type=0 ORDER BY id DESC LIMIT :limit");
			$request->bindValue(':limit', $limit, PDO::PARAM_INT);
			if ($request->execute()) {
				return $request->fetchAll();
			}
			return false;
		}

		public function insertProductData($proselect, $quantity, $date, $provider, $recipient, $remarks, $type)
		{
			try {
					$request = $this->dbh->prepare("INSERT INTO product (product_id, quantity, cdate, provider,recipient,remarks, type) VALUES(?,?,?,?,?,?,?) ");
					return $request->execute([$proselect, $quantity, $date, $provider, $recipient, $remarks, $type]);
			} catch (Exception $e) {
				return false;
			}
		}
		public function deleteProduction($id)
		{
			$request = $this->dbh->prepare("DELETE FROM product WHERE id = ?");
			return $request->execute([$id]);
		}

		/**
		 * production Status
		 */
		 public function fetchProductionStats($limit = 100)
		{
			$request = $this->dbh->prepare("SELECT n.product_id, n .name, IFNULL((n.received-s.sent),n.received) as quantity FROM (SELECT product_id,(SELECT pro_name FROM kp_products where pro_id= product_id) AS name, IFNULL(SUM(quantity),0) as received FROM product WHERE type=1 GROUP BY product_id) n LEFT JOIN (SELECT product_id, IFNULL(SUM(quantity),0) as sent FROM product WHERE type=0 GROUP BY product_id) s ON s.product_id = n.product_id");
			if ($request->execute()) {
				return $request->fetchAll();
			}
			return false;
		}
		
		

		/**
		 * production Status
		 */
		 public function fetchBilling($limit = 100)
		{
			$limit = (int) $limit;
			$request = $this->dbh->prepare("
			SELECT
				id,
				customer_id,
				package_id,
				r_month as months,
				amount as total,
				g_date,
				p_date,
				status
			FROM payments
			WHERE status IN ('Unpaid', 'Pending')
			ORDER BY id DESC
			LIMIT :limit
		");
			$request->bindValue(':limit', $limit, PDO::PARAM_INT);
			if ($request->execute()) {
				return $request->fetchAll();
			}
			return false;
		}
		 public function fetchindIvidualBill($customer_id)
		{
			$request = $this->dbh->prepare("SELECT * FROM `payments` where customer_id = ? and status = 'Unpaid'");
			if ($request->execute([$customer_id])) {
				return $request->fetchAll();
			}
			return false;
		}

		public function getPaymentById($id)
		{
			$request = $this->dbh->prepare("SELECT * FROM payments WHERE id = ?");
			if ($request->execute([$id])) {
				return $request->fetch();
			}
			return false;
		}

		/**
		 * Insert a row into payment_history to keep an immutable ledger of payments.
		 */
		public function insertPaymentHistoryEntry($payment, $paid_amount)
		{
			if (!$payment) {
				return false;
			}
			$paid_amount = (float)$paid_amount;
			if ($paid_amount <= 0) {
				return false;
			}
			$package_id = $payment->package_id;
			if (empty($package_id)) {
				$customer = $this->getCustomerInfo($payment->customer_id);
				$package_id = $customer ? $customer->package_id : null;
			}
			// Ensure employer attribution is correct for e-wallet payments.
			// If employer_id is missing on the payment row (typical for GCash/PayMaya),
			// derive it from the owning customer so the ledger shows the employer name.
			$history_employer_id = $payment->employer_id;
			if (empty($history_employer_id)) {
				$customer = isset($customer) ? $customer : $this->getCustomerInfo($payment->customer_id);
				$history_employer_id = $customer ? $customer->employer_id : null;
			}
			$request = $this->dbh->prepare("INSERT INTO payment_history (payment_id, customer_id, employer_id, package_id, r_month, amount, paid_amount, balance_after, payment_method, reference_number, paid_at) VALUES (?,?,?,?,?,?,?,?,?,?, NOW())");
			return $request->execute([
				$payment->id,
				$payment->customer_id,
				$history_employer_id,
				$package_id,
				$payment->r_month,
				(float)$payment->amount,
				$paid_amount,
				(float)$payment->balance,
				$payment->payment_method,
				$payment->reference_number,
			]);
		}

		public function fetchPaymentHistoryByCustomer($customer_id)
		{
			$request = $this->dbh->prepare("
				SELECT h.*, pkg.name AS package_name, u.full_name AS employer_name
				FROM payment_history h
				LEFT JOIN packages pkg ON h.package_id = pkg.id
				LEFT JOIN kp_user u ON h.employer_id = u.user_id
				WHERE h.customer_id = ?
				ORDER BY h.paid_at DESC, h.id DESC
			");
			if ($request->execute([$customer_id])) {
				return $request->fetchAll();
			}
			return false;
		}

		public function processPayment($payment_id, $payment_method, $reference_number, $amount_paid = null, $gcash_name = null, $gcash_number = null, $screenshot = null)
		{
			$payment = $this->getPaymentById($payment_id);
			if (!$payment) {
				return false;
			}

            $due_amount = ($payment->balance > 0) ? (float)$payment->balance : (float)$payment->amount;
            $paid_now = max(0.0, (float)$amount_paid);
            // Clamp to not go below zero
            if ($paid_now > $due_amount) {
                $paid_now = $due_amount;
            }
            $new_balance = $due_amount - $paid_now;

			$screenshot_path = null;
			if ($screenshot && $screenshot['error'] == UPLOAD_ERR_OK) {
				$upload_dir = 'uploads/screenshots/';
				if (!is_dir($upload_dir)) {
					mkdir($upload_dir, 0755, true);
				}
				$filename = uniqid() . '-' . preg_replace('/[^A-Za-z0-9.\-\_]/', '', basename($screenshot['name']));
				$screenshot_path = $upload_dir . $filename;
				move_uploaded_file($screenshot['tmp_name'], $screenshot_path);
			}

			// Preserve submitted amount for admin display in gcash_name when e-wallets are used
			$submitted_amount = (is_numeric($gcash_name) ? (float)$gcash_name : $paid_now);
			$request = $this->dbh->prepare("UPDATE payments SET status = 'Pending', balance = ?, payment_method = ?, reference_number = ?, gcash_name = ?, gcash_number = ?, screenshot = ? WHERE id = ?");
			return $request->execute([$new_balance, $payment_method, $reference_number, $submitted_amount, $gcash_number, $screenshot_path, $payment_id]);
		}

		public function processManualPayment($customer_id, $employer_id, $amount, $reference_number, $selected_bills, $screenshot = null)
		{
			$screenshot_path = null;
			if ($screenshot && $screenshot['error'] == UPLOAD_ERR_OK) {
				$upload_dir = 'uploads/screenshots/';
				if (!is_dir($upload_dir)) {
					mkdir($upload_dir, 0755, true);
				}
				$filename = uniqid() . '-' . preg_replace('/[^A-Za-z0-9.\-\_]/', '', basename($screenshot['name']));
				$screenshot_path = $upload_dir . $filename;
				move_uploaded_file($screenshot['tmp_name'], $screenshot_path);
			}

			try {
				$this->dbh->beginTransaction();

				$remaining_amount = (float)$amount;

                foreach ($selected_bills as $bill_id) {
					if ($remaining_amount <= 0) {
						break;
					}

					$bill = $this->getPaymentById($bill_id);
					if (!$bill) {
						continue;
					}

                    $due_amount = ($bill->balance > 0) ? (float)$bill->balance : (float)$bill->amount;

					if ($remaining_amount >= $due_amount) {
						$new_balance = 0;
						$payment_for_this_bill = $due_amount;
					} else {
						$new_balance = $due_amount - $remaining_amount;
						$payment_for_this_bill = $remaining_amount;
					}

					$request = $this->dbh->prepare(
						"UPDATE payments SET status = 'Pending', balance = ?, payment_method = 'Manual', employer_id = ?, reference_number = ?, screenshot = ?, gcash_name = ? WHERE id = ?"
					);
					$request->execute([
						$new_balance,
						$employer_id,
						$reference_number,
						$screenshot_path,
						$payment_for_this_bill,
						$bill_id
					]);



					$remaining_amount -= $payment_for_this_bill;
				}

				$this->dbh->commit();
				return true;
			} catch (Exception $e) {
				$this->dbh->rollBack();
				return false;
			}
		}

		public function approvePayment($payment_id)
		{
			$payment = $this->getPaymentById($payment_id);
			if (!$payment) {
				return false;
			}
			if ($payment->screenshot && file_exists($payment->screenshot)) {
				unlink($payment->screenshot);
			}
			// Determine the submitted amount for this approval
			$submitted_amount = 0.0;
			if (isset($payment->gcash_name) && is_numeric($payment->gcash_name)) {
				$submitted_amount = (float)$payment->gcash_name;
			} else {
				// Fallback: compute remaining unrecorded portion by subtracting any previously recorded amounts
				$sumRequest = $this->dbh->prepare("SELECT COALESCE(SUM(paid_amount),0) AS total_recorded FROM payment_history WHERE payment_id = ?");
				$sumRequest->execute([$payment_id]);
				$row = $sumRequest->fetch();
				$total_recorded = $row ? (float)$row->total_recorded : 0.0;
				$already_paid_total = (float)$payment->amount - (float)$payment->balance;
				$submitted_amount = max(0.0, $already_paid_total - $total_recorded);
			}

			// Insert a history entry for this payment approval
			if ($submitted_amount > 0) {
				$this->insertPaymentHistoryEntry($payment, $submitted_amount);
			}

			$new_status = ($payment->balance <= 0) ? 'Paid' : 'Unpaid';
			$request = $this->dbh->prepare("UPDATE payments SET status = ?, p_date = NOW(), screenshot = NULL, gcash_name = NULL, gcash_number = NULL WHERE id = ?");
			return $request->execute([$new_status, $payment_id]);
		}

        public function rejectPayment($payment_id)
        {
            $payment = $this->getPaymentById($payment_id);
            if (!$payment) {
                return false;
            }

            if ($payment->screenshot && file_exists($payment->screenshot)) {
                unlink($payment->screenshot);
            }

            // Restore balance to the state BEFORE the pending submission.
            // The submitted amount for this pending entry is stored temporarily
            // in gcash_name (for both Manual and e-wallet payments). If not
            // available, derive it from the ledger to avoid resetting prior
            // partial payments.
            $submitted_amount = 0.0;
            if (isset($payment->gcash_name) && is_numeric($payment->gcash_name)) {
                $submitted_amount = (float)$payment->gcash_name;
            } else {
                // Fallback: compute amount included in this pending update that
                // is not yet recorded in payment_history.
                $sumRequest = $this->dbh->prepare("SELECT COALESCE(SUM(paid_amount),0) AS total_recorded FROM payment_history WHERE payment_id = ?");
                $sumRequest->execute([$payment_id]);
                $row = $sumRequest->fetch();
                $total_recorded = $row ? (float)$row->total_recorded : 0.0;
                $already_paid_total = (float)$payment->amount - (float)$payment->balance; // includes the pending part
                $submitted_amount = max(0.0, $already_paid_total - $total_recorded);
            }

            // Current balance is (previous_due - submitted_amount). Add back the
            // submitted amount to restore the previous due, and clamp within [0, amount].
            $restore_balance = (float)$payment->balance + $submitted_amount;
            if ($restore_balance > (float)$payment->amount) {
                $restore_balance = (float)$payment->amount;
            }
            if ($restore_balance < 0) {
                $restore_balance = 0.0;
            }

            $request = $this->dbh->prepare("UPDATE payments SET status = 'Rejected', balance = ?, screenshot = NULL, gcash_name = NULL, gcash_number = NULL WHERE id = ?");
            return $request->execute([$restore_balance, $payment_id]);
        }
		
		public function getCustomerInfo($id)
		{
			$request = $this->dbh->prepare("SELECT * FROM customers WHERE id = ?");
			if ($request->execute([$id])) {
				return $request->fetch();
			}
			return false;
		}
		public function getPackageInfo($id)
		{
			$request = $this->dbh->prepare("SELECT * FROM packages WHERE id = ?");
			if ($request->execute([$id])) {
				return $request->fetch();
			}
			return false;
		}

		public function getPackages()
		{
			$request = $this->dbh->prepare("SELECT * FROM packages ORDER BY id");
			if ($request->execute()) {
				return $request->fetchAll();
			}
			return false;
		}

		public function deletePackage($id){
			$request = $this->dbh->prepare("DELETE FROM packages WHERE id = ?");
			return $request->execute([$id]);
		}

		public function updatePackage($id, $name, $price){
			$request = $this->dbh->prepare("UPDATE packages SET name = ?, fee = ? WHERE id = ?");
			return $request->execute([$name, $price, $id]);
		}

		public function addNewPackage($name, $price){
			try {
				$request = $this->dbh->prepare("INSERT INTO packages (name, fee) VALUES(?,?) ");
				return $request->execute([$name, $price]);
			} catch (Exception $e) {
				return false;
			}
		}
		
		/**
		 * Cash Collection
		 */
		 public function fetchCollectin($limit = 100)
		{
			$limit = (int) $limit;
			$request = $this->dbh->prepare("SELECT * FROM cash_collection LIMIT :limit");
			$request->bindValue(':limit', $limit, PDO::PARAM_INT);
			if ($request->execute()) {
				return $request->fetchAll();
			}
			return false;
		}
		/**
		 * Cash Expanse
		 */
		 public function fetchExpanse($limit = 100)
		{
			$limit = (int) $limit;
			$request = $this->dbh->prepare("SELECT * FROM cash_expanse LIMIT :limit");
			$request->bindValue(':limit', $limit, PDO::PARAM_INT);
			if ($request->execute()) {
				return $request->fetchAll();
			}
			return false;
		}
		
		/**
		* Insert Payment data
		*/
		public function billPay( $customer_id, $bill_id, $bill_months, $discount, $bill_amount )
		{	
			try {
				$this->dbh->beginTransaction();
					$request = $this->dbh->prepare("INSERT INTO billings (customer_id, bill_id, bill_month, discount, bill_amount) VALUES(?,?,?,?,?)");
					$request->execute([$customer_id, $bill_id, $bill_months, $discount, $bill_amount]);

					$values = explode(',', $bill_id);
					$placeholder = rtrim(str_repeat('?, ', count($values)), ', ');

					// For each payment, compute paid portion and insert into ledger
					foreach ($values as $pid) {
						$payment = $this->getPaymentById($pid);
						if ($payment) {
							$paid_amount = (float)$payment->amount; // settling in full via billPay
							$payment->balance = 0.0;
							$payment->payment_method = 'Admin';
							$payment->reference_number = null;
							$this->insertPaymentHistoryEntry($payment, $paid_amount);
						}
					}

					$request2 = $this->dbh->prepare("UPDATE payments SET status='Paid', balance = 0, p_date = NOW() WHERE id IN ($placeholder)");
					$request2->execute($values);
				$this->dbh->commit();
				return true;
			} catch (Exception $e) {
				$this->dbh->rollBack();
				return false;
			}
		}
		


	// Bill generation of a Month
		public function billGenerate($customer_id, $r_month, $amount){
			try {
				$request = $this->dbh->prepare("INSERT IGNORE INTO payments (customer_id, r_month, amount, balance) VALUES(?,?,?,?)");
				return $request->execute([$customer_id, $r_month, $amount, $amount]);
			} catch (Exception $e) {
				return false;
			}
		}
		public function getLastMonth($customer_id){
			$request = $this->dbh->prepare("SELECT r_month FROM payments WHERE customer_id = ? ORDER BY id DESC LIMIT 1");
			if ($request->execute([$customer_id])) {
				return $request->fetch();
			}
			return false;
		}
		public function fetchActiveCustomers(){
			$request = $this->dbh->prepare("SELECT * FROM `customers` where dropped = 0 ORDER BY id");
			if ($request->execute()) {
				return $request->fetchAll();
			}
			return false;
		}




		public function	fetchPaymentSlip($customer_id){
			$request = $this->dbh->prepare("SELECT * FROM `billings` where customer_id = ? LIMIT 1");
			if ($request->execute([$customer_id])) {
				return $request->fetch();
			}
			return false;
		}


	//Expance related functions
		public function expanse($amount, $for, $remarks){
			try {
				$request = $this->dbh->prepare("INSERT INTO cash_expanse (amount, purpose, remarks) VALUES(?,?,?)");
				return $request->execute([$amount, $for, $remarks]);
			} catch (Exception $e) {
				return false;
			}
		}
	//Collection related functions
		public function colleciton($amount, $from, $remarks){
			try {
				$request = $this->dbh->prepare("INSERT INTO cash_collection (amount, payee, remarks) VALUES(?,?,?)");
				return $request->execute([$amount, $from, $remarks]);
			} catch (Exception $e) {
				return false;
			}
		}




		public function getCategories()
		{
			$request = $this->dbh->prepare("SELECT * FROM kp_category ORDER BY cat_id");
			if ($request->execute()) {
				return $request->fetchAll();
			}
			return false;
		}

		public function deleteCategory($id){
			$request = $this->dbh->prepare("DELETE FROM kp_category WHERE cat_id = ?");
			return $request->execute([$id]);
		}

		public function updateCategory($id, $name){
			$request = $this->dbh->prepare("UPDATE kp_category SET cat_name = ? WHERE cat_id = ?");
			return $request->execute([$name, $id]);
		}

		public function addNewCategory($name){
			try {
				$request = $this->dbh->prepare("INSERT INTO kp_category (cat_name) VALUES(?) ");
				return $request->execute([$name]);
			} catch (Exception $e) {
				return false;
			}
		}



	// commented
		// /**
		//  * Raw Products
		//  */
		// public function addRawProduct($name, $unit, $details)
		// {
		// 	$request = $this->dbh->prepare("INSERT INTO kp_raw (raw_name, raw_unit, raw_details) VALUES(?,?,?) ");

		// 	return $request->execute([$name, $unit, $details]);
		// }

		// /**
		//  * Check if a raw product exist
		//  */
		// public function rawproductExists( $raw_name )
		// {
		// 	$request = $this->dbh->prepare("SELECT raw_name FROM kp_raw WHERE raw_name = ?");
		// 	$request->execute([$raw_name]);
		// 	$Admindata = $request->fetchAll();
		// 	return sizeof($Admindata) != 0;
		// }

		// /**
		//  * Edit a product
		//  */

		// public function updateRawProduct($id, $name, $unit, $details)
		// {
		// 	$request = $this->dbh->prepare("UPDATE kp_raw SET raw_name = ?, raw_unit = ?, raw_details = ? WHERE raw_id = ? ");

		// 	// Do not forget to encrypt the pasword before saving
		// 	return $request->execute([$name, $unit, $details, $id]);
		// }




		/**
		 * Fetch products
		 */
		
		// public function fetchChartData()
		// {
		// 	$request = $this->dbh->prepare("SELECT pro_id, raw_quantity FROM kp_raw  ORDER BY raw_id");
		// 	if ($request->execute()) {
		// 		return $request->fetchAll();
		// 	}
		// 	return false;
		// }



		// /**
		//  * Fetch raw products
		//  */
		// public function fetchrawProducts($limit = 100)
		// {
		// 	$request = $this->dbh->prepare("SELECT * FROM kp_raw  ORDER BY raw_id  LIMIT $limit");
		// 	if ($request->execute()) {
		// 		return $request->fetchAll();
		// 	}
		// 	return false;
		// }



		// /**
		//  *	Fetch a raw product
		//  */

		// public function getArawProduct($id)
		// {
		// 	$request = $this->dbh->prepare("SELECT * FROM kp_raw WHERE raw_id = ?");
		// 	if ($request->execute([$id])) {
		// 		return $request->fetch();
		// 	}
		// 	return false;
		// }



		/*
		 *	Delete a raw product
		 */

		// public function deleterawProduct($id)
		// {
		// 	$request = $this->dbh->prepare("DELETE FROM kp_raw WHERE raw_id = ?");
		// 	return $request->execute([$id]);
		// }

		// /**
		// * Insert product data
		// */
		// public function insertProductData( $proselect, $production, $date, $finished, $unfinished )
		// {
		// 	try {
		// 		$this->dbh->beginTransaction();
		// 			$request = $this->dbh->prepare("INSERT INTO kp_production (pro_id, pro_qty, date, pro_fin, pro_unfin) VALUES(?,?,?,?,?)");
		// 			$request->execute([$proselect, $production, $date, $finished, $unfinished]);

		// 			$request2 = $this->dbh->prepare("UPDATE pro_finished SET pro_qty = pro_qty+? WHERE pro_id = ?");
		// 			$request2->execute([$finished, $proselect]);

		// 			$request3 = $this->dbh->prepare("UPDATE pro_unfinished SET pro_qty = pro_qty+? WHERE pro_id = ?");
		// 			$request3->execute([$unfinished, $proselect]);
		// 		$this->dbh->commit();
		// 		return true;
		// 	} catch (Exception $e) {
		// 		$this->dbh->rollBack();
		// 		return false;
		// 	}
		// }
		/*
		 *	Delete a raw product
		 */

		// public function deleteProduction($id)
		// {
		// 	try {
		// 			// $this->dbh->beginTransaction();
		// 			// $request1 = $this->dbh->prepare("SELECT id, pro_id, pro_qty FROM kp_production WHERE id = ?");
		// 			// $request1->execute([$id]);
		// 			// $request1->fetch(PDO::FETCH_CLASS,'Admin');
		// 			// $quantity = $this->request1->pro_qty;
		// 			// $pro_id = $this->request1->pro_id;

		// 			// $request2 = $this->dbh->prepare("UPDATE pro_finished SET pro_qty = (pro_qty-?) WHERE pro_id = ?");
		// 			// $request2->execute([$quantity, $pro_id]);

		// 			$request = $this->dbh->prepare("DELETE FROM kp_production WHERE id = ?");
		// 			$request->execute([$id]);
		// 			// $this->dbh->commit();
		// 		return true;
		// 	} catch (Exception $e) {
		// 		//$this->dbh->rollBack();
		// 		return false;
		// 	}
		// }

		// /**
		// * Insert Raw data stat
		// */
		// public function insertRawData($raw_id, $date, $used, $purchased, $available)
		// {
		// 	try {
		// 		$this->dbh->beginTransaction();
		// 			$request = $this->dbh->prepare("INSERT INTO raw_stocking (raw_id, date, raw_purchesed, raw_used ) VALUES(?,?,?,?)");
		// 			$request->execute([$raw_id, $date, $purchased, $used]);

		// 			$request2 = $this->dbh->prepare("UPDATE kp_raw SET raw_quantity = raw_quantity+? WHERE raw_id = ?");
		// 			$request2->execute([$available, $raw_id]);
		// 		$this->dbh->commit();
		// 		return true;
		// 	} catch (Exception $e) {
		// 		$this->dbh->rollBack();
		// 		return false;
		// 	}
		// }

		/*
		 *	Delete a raw product
		 */

		// public function deleteRawData($id)
		// {
		// 	try {
		// 			// $this->dbh->beginTransaction();
		// 			// $request1 = $this->dbh->prepare("SELECT id, pro_id, pro_qty FROM kp_production WHERE id = ?");
		// 			// $request1->execute([$id]);
		// 			// $request1->fetch(PDO::FETCH_CLASS,'Admin');
		// 			// $quantity = $this->request1->pro_qty;
		// 			// $pro_id = $this->request1->pro_id;

		// 			// $request2 = $this->dbh->prepare("UPDATE pro_finished SET pro_qty = (pro_qty-?) WHERE pro_id = ?");
		// 			// $request2->execute([$quantity, $pro_id]);

		// 			$request = $this->dbh->prepare("DELETE FROM raw_stocking WHERE id = ?");
		// 			$request->execute([$id]);
		// 			// $this->dbh->commit();
		// 		return true;
		// 	} catch (Exception $e) {
		// 		//$this->dbh->rollBack();
		// 		return false;
		// 	}
		// }


		// /*production to stocking table*/
		// public function insertProductionData( $proselect, $sold, $date, $waste, $return )
		// {	
		// 	$availableProducts = ($sold+$waste)-$return;
		// 	try {
		// 		$this->dbh->beginTransaction();
		// 			$request = $this->dbh->prepare("INSERT INTO kp_stocking (pro_id, date, pro_sold, pro_waste, pro_return) VALUES(?,?,?,?,?)");
		// 			$request->execute([$proselect, $date, $sold, $waste, $return]);

		// 			$request2 = $this->dbh->prepare("UPDATE pro_finished SET pro_qty = pro_qty-? WHERE pro_id = ?");
		// 			$request2->execute([$availableProducts, $proselect]);

		// 		$this->dbh->commit();
		// 		return true;
		// 	} catch (Exception $e) {
		// 		$this->dbh->rollBack();
		// 		return false;
		// 	}
		// }


		/*
		 *	Delete a raw product
		 */

		// public function deleteStocking($id)
		// {
		// 	try {
		// 			// $this->dbh->beginTransaction();
		// 			// $request1 = $this->dbh->prepare("SELECT id, pro_id, pro_qty FROM kp_production WHERE id = ?");
		// 			// $request1->execute([$id]);
		// 			// $request1->fetch(PDO::FETCH_CLASS,'Admin');
		// 			// $quantity = $this->request1->pro_qty;
		// 			// $pro_id = $this->request1->pro_id;

		// 			// $request2 = $this->dbh->prepare("UPDATE pro_finished SET pro_qty = (pro_qty-?) WHERE pro_id = ?");
		// 			// $request2->execute([$quantity, $pro_id]);

		// 			$request = $this->dbh->prepare("DELETE FROM kp_stocking WHERE id = ?");
		// 			$request->execute([$id]);
		// 			// $this->dbh->commit();
		// 		return true;
		// 	} catch (Exception $e) {
		// 		//$this->dbh->rollBack();
		// 		return false;
		// 	}
		// }

		/*
		*Fetch production from database
		*/
		// public function fetchProduction($limit = 100)
		// {
		// 	$request = $this->dbh->prepare("SELECT * FROM kp_production  ORDER BY id DESC LIMIT $limit");
		// 	if ($request->execute()) {
		// 		return $request->fetchAll();
		// 	}
		// 	return false;
		// }

		// /**
		// *Get list of finished products
		// */
		// public function getfinishedProduct($id)
		// {
		// 		$request = $this->dbh->prepare("SELECT * FROM pro_finished WHERE pro_id = ?");
		// 		if ($request->execute([$id])) {
		// 			return $request->fetch();
		// 		}
		// 		return false;
		// }

		// /**
		// * Get list of unfinished products
		// *
		// */
		// public function getunfinishedProduct($id)
		// {
		// 		$request = $this->dbh->prepare("SELECT * FROM pro_unfinished WHERE pro_id = ?");
		// 		if ($request->execute([$id])) {
		// 			return $request->fetch();
		// 		}
		// 		return false;
		// }

		/*
		*	Fetch production from database
		*/
		// public function fetchProductionData($limit = 100)
		// {
		// 	$request = $this->dbh->prepare("SELECT * FROM kp_stocking  ORDER BY id DESC LIMIT $limit");
		// 	if ($request->execute()) {
		// 		return $request->fetchAll();
		// 	}
		// 	return false;
		// }


		// /**
		//  * Fetch raw products
		//  */
		// public function fetchrawEntry($limit = 100)
		// {
		// 	$request = $this->dbh->prepare("SELECT * FROM raw_stocking  ORDER BY id  LIMIT $limit");
		// 	if ($request->execute()) {
		// 		return $request->fetchAll();
		// 	}
		// 	return false;
		// }

	}
