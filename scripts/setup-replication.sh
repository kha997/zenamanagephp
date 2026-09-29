#!/bin/bash

# Database Replication Setup Script
# This script sets up MySQL master-slave replication

set -e

# GAP-056: MySQL credentials via 0600 option files, never on the command line.
source "$(dirname "${BASH_SOURCE[0]}")/lib/mysql-credentials.sh"

# Configuration
MASTER_HOST=${MASTER_HOST:-"mysql"}
SLAVE_HOST=${SLAVE_HOST:-"mysql_read"}
MASTER_USER=${MASTER_USER:-"root"}
: "${MASTER_PASSWORD:?Set MASTER_PASSWORD (do NOT use defaults)}"  # GAP-056
REPLICATION_USER=${REPLICATION_USER:-"replicator"}
: "${REPLICATION_PASSWORD:?Set REPLICATION_PASSWORD (do NOT use defaults)}"
DATABASE=${DATABASE:-"zenamanage"}

echo "Setting up MySQL replication..."
echo "Master: $MASTER_HOST"
echo "Slave: $SLAVE_HOST"
echo "Database: $DATABASE"

# Function to wait for MySQL to be ready
wait_for_mysql() {
    local host=$1
    local user=$2
    local password=$3
    
    echo "Waiting for MySQL at $host to be ready..."
    mysql_option_file DB_CNF "$user" "$password"
    while ! mysql --defaults-extra-file="$DB_CNF" -h "$host" -u "$user" -e "SELECT 1;" >/dev/null 2>&1; do
        echo "MySQL at $host is not ready yet. Waiting..."
        sleep 5
    done
    echo "MySQL at $host is ready!"
}

# Function to setup master
setup_master() {
    echo "Setting up master database..."
    
    mysql_option_file DB_CNF "$MASTER_USER" "$MASTER_PASSWORD"
    mysql --defaults-extra-file="$DB_CNF" -h "$MASTER_HOST" -u "$MASTER_USER" <<EOF
-- Create replication user
CREATE USER IF NOT EXISTS '$REPLICATION_USER'@'%' IDENTIFIED BY '$REPLICATION_PASSWORD';
GRANT REPLICATION SLAVE ON *.* TO '$REPLICATION_USER'@'%';
FLUSH PRIVILEGES;

-- Show master status
SHOW MASTER STATUS;
EOF

    echo "Master setup completed!"
}

# Function to setup slave
setup_slave() {
    echo "Setting up slave database..."
    
    # Get master status
    mysql_option_file DB_CNF "$MASTER_USER" "$MASTER_PASSWORD"
    MASTER_STATUS=$(mysql --defaults-extra-file="$DB_CNF" -h "$MASTER_HOST" -u "$MASTER_USER" -e "SHOW MASTER STATUS\G" | grep -E "(File|Position)")
    MASTER_LOG_FILE=$(echo "$MASTER_STATUS" | grep "File:" | awk '{print $2}')
    MASTER_LOG_POS=$(echo "$MASTER_STATUS" | grep "Position:" | awk '{print $2}')
    
    echo "Master log file: $MASTER_LOG_FILE"
    echo "Master log position: $MASTER_LOG_POS"
    
    mysql_option_file DB_CNF "$MASTER_USER" "$MASTER_PASSWORD"
    mysql --defaults-extra-file="$DB_CNF" -h "$SLAVE_HOST" -u "$MASTER_USER" <<EOF
-- Stop slave
STOP SLAVE;

-- Configure slave
CHANGE MASTER TO
    MASTER_HOST='$MASTER_HOST',
    MASTER_USER='$REPLICATION_USER',
    MASTER_PASSWORD='$REPLICATION_PASSWORD',
    MASTER_LOG_FILE='$MASTER_LOG_FILE',
    MASTER_LOG_POS=$MASTER_LOG_POS;

-- Start slave
START SLAVE;

-- Show slave status
SHOW SLAVE STATUS\G
EOF

    echo "Slave setup completed!"
}

# Function to verify replication
verify_replication() {
    echo "Verifying replication..."
    
    # Check slave status
    mysql_option_file DB_CNF "$MASTER_USER" "$MASTER_PASSWORD"
    SLAVE_STATUS=$(mysql --defaults-extra-file="$DB_CNF" -h "$SLAVE_HOST" -u "$MASTER_USER" -e "SHOW SLAVE STATUS\G")
    
    if echo "$SLAVE_STATUS" | grep -q "Slave_IO_Running: Yes" && echo "$SLAVE_STATUS" | grep -q "Slave_SQL_Running: Yes"; then
        echo "✅ Replication is working correctly!"
    else
        echo "❌ Replication is not working properly!"
        echo "Slave status:"
        echo "$SLAVE_STATUS"
        exit 1
    fi
}

# Function to test replication
test_replication() {
    echo "Testing replication..."
    
    # Create test table on master
    mysql_option_file DB_CNF "$MASTER_USER" "$MASTER_PASSWORD"
    mysql --defaults-extra-file="$DB_CNF" -h "$MASTER_HOST" -u "$MASTER_USER" "$DATABASE" <<EOF
CREATE TABLE IF NOT EXISTS replication_test (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
EOF

    # Insert test data
    mysql_option_file DB_CNF "$MASTER_USER" "$MASTER_PASSWORD"
    mysql --defaults-extra-file="$DB_CNF" -h "$MASTER_HOST" -u "$MASTER_USER" "$DATABASE" <<EOF
INSERT INTO replication_test (message) VALUES ('Test replication at $(date)');
EOF

    # Wait a moment for replication
    sleep 2

    # Check if data exists on slave
    mysql_option_file DB_CNF "$MASTER_USER" "$MASTER_PASSWORD"
    SLAVE_COUNT=$(mysql --defaults-extra-file="$DB_CNF" -h "$SLAVE_HOST" -u "$MASTER_USER" "$DATABASE" -e "SELECT COUNT(*) FROM replication_test;" -s -N)
    
    if [ "$SLAVE_COUNT" -gt 0 ]; then
        echo "✅ Replication test passed! Data replicated successfully."
    else
        echo "❌ Replication test failed! Data not found on slave."
        exit 1
    fi

    # Clean up test table
    mysql_option_file DB_CNF "$MASTER_USER" "$MASTER_PASSWORD"
    mysql --defaults-extra-file="$DB_CNF" -h "$MASTER_HOST" -u "$MASTER_USER" "$DATABASE" <<EOF
DROP TABLE IF EXISTS replication_test;
EOF
}

# Main execution
main() {
    echo "Starting MySQL replication setup..."
    
    # Wait for both MySQL instances to be ready
    wait_for_mysql "$MASTER_HOST" "$MASTER_USER" "$MASTER_PASSWORD"
    wait_for_mysql "$SLAVE_HOST" "$MASTER_USER" "$MASTER_PASSWORD"
    
    # Setup master
    setup_master
    
    # Setup slave
    setup_slave
    
    # Verify replication
    verify_replication
    
    # Test replication
    test_replication
    
    echo "🎉 MySQL replication setup completed successfully!"
}

# Run main function
main "$@"
